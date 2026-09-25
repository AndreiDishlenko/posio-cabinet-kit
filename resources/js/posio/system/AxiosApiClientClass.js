import axios            from "axios"
import { Emitter }      from './Emitter.js'
import { deviceLog }    from '@/js/DeviceLog'

// Пауза интерфейса на время запроса: подходит кабинету, где обращение к серверу —
// часть действия пользователя, и вредна кассе, где сеть фоновая. Ключ инициатора
// уникален на запрос, иначе экран разблокирует первый ответивший из пачки.
let pause_counter = 0;

// Сколько не трогаем авторизацию после отказа сети: без паузы каждый фоновый
// запрос офлайн заново дёргает вход и плодит бесполезные попытки.
const SIGNIN_RETRY_COOLDOWN = 10000;

// Сколько верим браузерному «сети нет» после попытки, которая это подтвердила.
// Дальше снова пробуем: сам признак бывает ложным, а без попытки ложь не вскроется.
const OFFLINE_CONFIRM_WINDOW = 30000;

// Состояние общее для всех клиентов: признак связи у браузера один на страницу.
const browser_offline_flag = {
    confirmed_at: 0,
    // Сервер ответил, пока браузер утверждал, что сети нет (старые iOS, симулятор,
    // VPN) — до перезагрузки признаку не верим, иначе обмен встал бы навсегда.
    unreliable:   false,
};

function browserReportsOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
}

// Ответ сервера (любой код), а не сохранённая копия воркера — значит, связь есть.
function noteServerReached(response) {
    if ( !response || response.headers?.['x-sw-cache'] )
        return;

    if ( !browserReportsOffline() || browser_offline_flag.unreliable )
        return;

    browser_offline_flag.unreliable = true;
    deviceLog('sync').warn('[AxiosApiClient] browser reports offline, but server responded — ignoring browser flag for this session');
}

function noteNetworkUnreachable() {
    if ( browserReportsOffline() )
        browser_offline_flag.confirmed_at = Date.now();
}

export class AxiosApiClientClass {

    options
    axios
    custom_headers
    access_token
    signin_request
    signin_failed_at

    constructor(options={}) {
        // console.log('[AxiosApiClient.constructor]', url);
        this.main_init(options)
    }

    main_init(options={}) {
        this.options = options;

        let params = {
            timeout: 5000, 					// default
            headers: {
                'Content-Type': 'application/json',
            },
            withCredentials: true,
			...options
        };

        // if (this.options.disableCredentials) 
        //     params['withCredentials'] = false;

        // console.log('[AxiosApiClient.main_init', params);

        this.axios = axios.create(params);

        // Mix custom headers
        this.custom_headers = {}
        this.defineInterceptors()
    }

    defineInterceptors() {
        this.axios.interceptors.request.use(
            (config) => {
                config.headers = {
                    ...config.headers,
                    ...this.custom_headers,
                };

                return config;
            },
            (error) => Promise.reject(error)
        );

        this.axios.interceptors.response.use(
            (response) => {
                noteServerReached(response);

                // X-SW-Cache means response came from SW cache, not real network
                if (response.headers['x-sw-cache']) {
                    // «Данные старые, а ошибки нет» — самый частый класс жалоб:
                    // ответ отдала сохранённая копия, и связи при этом не было.
                    deviceLog('sync').debug('[AxiosApiClient] served from worker cache:', response.config?.url);
                }
                else
                    this.broadcastOfflineStatus(false);

                return response;
            },
            (error) => {
                if ( error.response )
                    noteServerReached(error.response);
                else if ( !axios.isCancel(error) )
                    noteNetworkUnreachable();

                if (!error.response || [0, 504, 503, 502].includes(error.response?.status))
                    this.broadcastOfflineStatus(true);
                return Promise.reject(error);
            }
        );
    }

    // Передаётся признак ОТСУТСТВИЯ связи: имя метода про online противоречило
    // значению аргумента и читалось наоборот на каждом вызове.
    broadcastOfflineStatus(offline) {
        // Внутри самой страницы признак раздаётся напрямую. Вещание между
        // контекстами Apple понимает только с 15.4, и ниже планки кассы состояние
        // связи не доходило до шапки вовсе: знак обмена оставался серым всю смену.
        Emitter.emit('offline_mode', offline);

        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('channel4');
            channel.postMessage({key: 'offline_mode', value: offline});
            channel.close();
        }
    }

    async authenticate() {
        throw new Error('[ApiClient] Method authenticate must be implemented in childs')
    }

    setCustomHeader(key, value) {
        // console.log('setHeader', key, value);
        this.custom_headers[key] = value;
    }

    removeCustomHeader(key) {
        delete this.custom_headers[key];
    }

    pauseRequest(options={}) {
        if ( options.disable_pause || this.options.pause_requests === false )
            return null;

        const token = `req-${++pause_counter}`;
        Emitter.emit('pause_application', token);

        return token;
    }

    unpauseRequest(token) {
        if ( token )
            Emitter.emit('unpause_application', token);
    }

    // Сеть не ответила вовсе (обрыв, таймаут, шлюз) — это не отказ в доступе, и
    // реагировать на него как на отказ нельзя: офлайн-касса легитимна.
    isNetworkFailure(response) {
        const code = response?.statusCode;
        return !code || [0, 502, 503, 504].includes(code);
    }

    // Опережаем ожидание только на браузерном «сети нет»: «сеть есть» — лишь наличие
    // интерфейса. Без этого каждый запрос офлайн стоит целой планки ожидания (15 с у
    // кассы, 30 с у справочников).
    //
    // Но и отрицанию слепо не верим: на части устройств признак ложный, и касса без
    // единой попытки не узнала бы, что связь есть. Поэтому пропускаем запросы, пока
    // браузерное «сети нет» не подтверждено недавней неудачной попыткой.
    isDisconnected() {
        // Витрина отвечает себе сама подменённым транспортом — связь ей не нужна.
        if ( this.options?.adapter )
            return false;

        if ( !browserReportsOffline() || browser_offline_flag.unreliable )
            return false;

        return Date.now() - browser_offline_flag.confirmed_at < OFFLINE_CONFIRM_WINDOW;
    }

    // Тот же вид, что у отказа сети из перехватчика: вызывающий код уже умеет его
    // читать и отличать от отказа сервера.
    disconnectedResponse(urlPrefix) {
        deviceLog('sync').debug('[AxiosApiClient] request skipped — device is offline:', urlPrefix);
        this.broadcastOfflineStatus(true);

        return {
            statusCode: 0,
            // Признак «запрос вообще не отправляли» отличает решение самого браузера
            // от неудачной попытки дозвониться: снаружи оба выглядят как код 0, а
            // причина у них разная и проверять их нужно в разных местах.
            offline: true,
            error: 'Network unavailable',
            message: 'Network unavailable',
            errors: [],
            data: {}
        };
    }

    // Авторизация в одном экземпляре: запросы стартового пакета летят параллельно и
    // на протухшем токене все разом попросили бы новый — это лишние обращения и
    // несколько одинаковых окон о лицензии подряд.
    async signin() {
        // console.msg('[AxiosApiClient.signin]', this.options.signin_payload);
        if ( this.signin_request )
            return this.signin_request;

        this.signin_request = this.requestNewToken()
            .finally(() => { this.signin_request = null; });

        return this.signin_request;
    }

    async requestNewToken() {
        let response = await this.postRaw(`/signin`, this.options.signin_payload ?? {})

        // Отсутствие связи не является отказом сервера: молча уходим без токена,
        // не показывая диалогов про лицензию — касса продолжит работать локально.
        if ( this.isNetworkFailure(response) ) {
            this.signin_failed_at = Date.now();
            deviceLog('sync').warn('[AxiosApiClient] Sign in skipped — no connection');
            return null;
        }

        this.checkSigninResponse(response);

        if ( response.error )
            throw new Error(`[AxiosApiClient] Authentication error. ${response.message}`);

        const token = response.data.access_token;
        localStorage.setItem('_api_access_token', token);
        return token;
    }

    async getToken(renew = false) {
        // console.log('[AxiosApiClient.getToken]');
        if ( renew )
            this.forgetToken();

        if ( this.access_token )
            return this.access_token;

        const cached = localStorage.getItem('_api_access_token');
        if (cached) {
            this.access_token = cached;
            return this.access_token;
        }

        if ( this.signin_failed_at && Date.now() - this.signin_failed_at < SIGNIN_RETRY_COOLDOWN )
            return null;

        this.access_token = await this.signin();

        return this.access_token;
    }

    forgetToken() {
        this.access_token = null;
        localStorage.removeItem('_api_access_token');
    }

    // Сервер мог отозвать токен, пока устройство держало его в локальном хранилище
    // (сменился отпечаток, токен вычистили) — без повторной авторизации оно осталось
    // бы с мёртвым токеном навсегда, получая отказ на каждом запросе. Ровно одна
    // попытка: на честном отказе доступа цикл повторов бесполезен.
    async renewedToken(response, options={}) {
        if ( !this.options.tokenAuthorization || options.keep_token )
            return null;

        if ( response?.statusCode !== 401 )
            return null;

        try {
            return await this.getToken(true);
        } catch (e) {
            // Без токена устройство остаётся без обмена вообще — до перезапуска
            // или до вмешательства, поэтому исход фатальный, а не досадный.
            deviceLog('sync').error('[AxiosApiClient] Unable to renew access token:', e.message);
            return null;
        }
    }

    async get(urlPrefix, url_params={}, customHeaders={}, options={}) {
        // console.log('[ApiClient.get]', urlPrefix, options);
        let response = {};

        const pause_token = this.pauseRequest(options);

        try {
            if ( this.options.tokenAuthorization ) {
                const token = await this.getToken();
                // Пустой заголовок ушёл бы строкой "null" и превратил отсутствие
                // связи в невнятный отказ валидации на сервере.
                if ( token )
                    customHeaders['X-Token'] = token;
            }

            response = await this.sendGet(urlPrefix, url_params, customHeaders, options);

            const renewed_token = await this.renewedToken(response, options);
            if ( renewed_token ) {
                customHeaders['X-Token'] = renewed_token;
                response = await this.sendGet(urlPrefix, url_params, customHeaders, { ...options, keep_token: true });
            }
        } finally {
            this.unpauseRequest(pause_token);
        }

        return response;
    }

    async sendGet(urlPrefix, url_params={}, customHeaders={}, options={}) {
        let response = {};

        if ( this.isDisconnected() )
            return this.disconnectedResponse(urlPrefix);

        // Тяжёлые выборки (словари) на мобильной связи не укладываются в общий
        // таймаут клиента — вызывающий код задаёт свою планку поверх него.
        const axios_config = { params: url_params, headers: customHeaders };
        if ( options.timeout )
            axios_config.timeout = options.timeout;

        try {
            let axios_response = await this.axios.get(urlPrefix, axios_config)
            response = this.getSuccessResponse(axios_response);

            this.checkResponse(response)
            await this.checkFullResponse(axios_response)
            // this.serverRequests(axios_response)
        } catch (axios_error) {
            // console.log('error', error);
            response = this.getErrorResponse(axios_error);
            this.checkRedirect(response)
            this.checkResponse(response)
            if (options.strictMode)
                throw new Error(axios_error)
        }

        return response;
    }

    async post(urlPrefix, customData, customHeaders={}, axiosParams, options) {
        // console.log('[ApiClient.post]', urlPrefix);
        if ( this.options.tokenAuthorization ) {
            const token = await this.getToken();
            if ( token )
                customHeaders['X-Token'] = token;
        }

        let result = await this.postRaw(urlPrefix, customData, customHeaders, axiosParams, options)

        const renewed_token = await this.renewedToken(result, options);
        if ( renewed_token ) {
            customHeaders['X-Token'] = renewed_token;
            result = await this.postRaw(urlPrefix, customData, customHeaders, axiosParams, { ...(options ?? {}), keep_token: true })
        }

        return result;
    }

	async postRaw(urlPrefix, customData={}, customHeaders={}, axiosParams = {}, options = {}) {
		//     console.log('[ApiClient.postRaw]', urlPrefix, customData);
		let response = {}

		if ( this.isDisconnected() )
			return this.disconnectedResponse(urlPrefix);

		const pause_token = this.pauseRequest(options);

		try {
			const config = {
				...axiosParams,
				headers: {
					...axiosParams.headers,
					...customHeaders
				}
			}

			// console.log('config:', config);
			// console.log('customData перед отправкой:', JSON.stringify(customData));

			let axios_response = await this.axios.post(urlPrefix, customData, config)

			response = this.getSuccessResponse(axios_response);
			// Проверке отдаётся нормализованный ответ, как и в ветке ошибки: с
			// сырым она читала .status вместо .statusCode и не срабатывала.
			this.checkResponse(response)
			await this.checkFullResponse(axios_response)

		} catch (axios_error) {
			response = this.getErrorResponse(axios_error);
			this.checkResponse(response)
			this.checkRedirect(response)
			if (options.strictMode) {
				this.unpauseRequest(pause_token);
				throw new Error(axios_error)
			}
		}

		this.unpauseRequest(pause_token);

		return response
	}

    // async postRaw(urlPrefix, customData={}, customHeaders={}, axiosParams = {}, options = {}) {
    //     console.log('[ApiClient.postRaw]', urlPrefix, customData); 
    //     let response = {}

    //     if ( !options.disable_pause )
    //         Emitter.emit('pause_application');

    //     try {
    //         axiosParams.headers = {
    //             ...axiosParams.headers,
    //             ...customHeaders
    //         }

	// 		console.log('axiosParams:', axiosParams);
	// 		console.log('customData перед отправкой:', JSON.stringify(customData));

    //         let axios_response = await this.axios.post(urlPrefix, customData, { 
	// 				axiosParams, 
	// 				headers: customHeaders
	// 			})           
    //         response = this.getSuccessResponse(axios_response);

    //         this.checkResponse(axios_response)
    //         await this.checkFullResponse(axios_response)
    //         // this.serverRequests(axios_response)
    //     } catch (axios_error) {
    //         // console.log('axios_post_error', axios_error);            
    //         response = this.getErrorResponse(axios_error);
    //         this.checkResponse(response)
    //         this.checkRedirect(response)
    //         if (options.strictMode)
    //             throw new Error(axios_error)
    //     }

    //     if ( !options.disable_pause )
    //         Emitter.emit('unpause_application');

    //     return response
    // }

    getSuccessResponse(axios_response) {
        // console.log('[ApiClient.getSuccessResponse]', axios_response);
        const contentType = axios_response.headers?.['content-type'] ?? '';
        if ( !contentType.includes('application/json') ) {
            // Обычно это страница-заглушка провайдера вместо ответа API: запрос
            // «прошёл», а данных нет — по коду ответа этого не видно.
            deviceLog('sync').error(`[AxiosApiClient] Unexpected Content-Type "${contentType}" — expected JSON`);
            return {
                statusCode: axios_response.status,
                error: `Unexpected response format: ${contentType}`,
                data: {}
            };
        }
        return {
            statusCode: axios_response.status,
            data: axios_response.data
        };
    }

    getErrorResponse(axios_error) {
        // console.log('[ApiClient.getErrorResponse]', axios_error);        
        return {
            statusCode: this.getErrorStatus(axios_error),
            error: this.getError(axios_error),
            message: axios_error.response?.data?.message || this.getError(axios_error),
            errors: axios_error.response?.data?.errors || [],
            data: axios_error.response?.data ?? {}
        };
    }

    getErrorStatus(axiosError) {
        return axiosError.status ?? 0
    }

    getError(axiosError) {  
        // console.log('getError', axiosError);    
        let brokenCodes = [500, 504, 599];
        if (brokenCodes.includes(axiosError.statusCode)) //! May be must be response instead of error
            return 'Broken connection';
            
        if (axiosError.response?.data?.error)
            return `${axiosError.response.data.error}`;

        if (axiosError.status==422)
            return `Validation error (422)`

        return axiosError.message
    }

    checkRedirect(error_result) {
        if (error_result.statusCode === 401) {
            const redirectUrl = error_result.data?.redirect;
            if (redirectUrl) {
                window.location.href = redirectUrl;
                throw new Error('The session time has expired.')
            }
        }
    }

    checkSigninResponse(response) {
    }

    checkResponse(response) {
        // console.log('[AxiosApiClient.checkResponse');        
        // console.warn('[AxiosApiClient] Please define checkResponse method');
    }

    async checkFullResponse(axios_answer) {
        // console.log('[AxiosApiClient.checkResponse');        
        // console.warn('[AxiosApiClient] Please define checkResponse method');
    }

    // async serverRequests(response) {
    //     // console.log('[AxiosApiClient.sendLogs]'); 
    //     if (response.headers['x-request-logs']) {            
    //         let response = this.post( route('cashbox.sendlogs'), { logs: console.logs } );
    //     }
    //     if (response.headers['x-request-clean']) {            
    //         console.warn('x-request-clean');
            
    //         // let response = this.post( route('cashbox.sendlogs'), { logs: console.logs } );
    //     }
    // }
}
