import axios            from "axios"
import { Emitter }      from './Emitter.js'

// Пауза интерфейса на время запроса: подходит кабинету, где обращение к серверу —
// часть действия пользователя, и вредна кассе, где сеть фоновая. Ключ инициатора
// уникален на запрос, иначе экран разблокирует первый ответивший из пачки.
let pause_counter = 0;

// Сколько не трогаем авторизацию после отказа сети: без паузы каждый фоновый
// запрос офлайн заново дёргает вход и плодит бесполезные попытки.
const SIGNIN_RETRY_COOLDOWN = 10000;

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
                // X-SW-Cache means response came from SW cache, not real network
                if (!response.headers['x-sw-cache'])
                    this.broadcastOnlineStatus(false);
                return response;
            },
            (error) => {
                if (!error.response || [0, 504, 503, 502].includes(error.response?.status))
                    this.broadcastOnlineStatus(true);
                return Promise.reject(error);
            }
        );
    }

    broadcastOnlineStatus(offline) {
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
            console.warn('[AxiosApiClient] Sign in skipped — no connection');
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
            console.warn('[AxiosApiClient] Unable to renew access token');
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
			this.checkResponse(axios_response)
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
            console.warn(`[AxiosApiClient] Unexpected Content-Type "${contentType}" — expected JSON`);
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
