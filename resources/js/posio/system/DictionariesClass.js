import { PropObjectClass }  from "../classes/PropObjectClass.js";
import { Toast }            from '@/js/posio/system/ToastMessages'
import { $t }               from '@/js/i18n.config'

// Полная выборка (товары, модификаторы, цены, комментарии) на мобильной связи
// не укладывается в общий таймаут клиента, а обрыв стоит дорого — до правки он
// оставлял кассу без словарей.
const UPDATE_TIMEOUT = 30000;

class DictionariesClass extends PropObjectClass {
    isUpdated
    apiClient
    url

    constructor(storage_name, apiClient, url) { 
        super()

        this.storage_name = storage_name ?? 'dictionaries';
        this.apiClient = apiClient;
        this.isUpdated = false;
        this.url = url;

        // Биндим один раз, чтобы add/removeEventListener работали с одной
		// ссылкой, а внутри обработчика this был экземпляром (а не window).
		this.onDictionariesUpdate = this.onDictionariesUpdate.bind(this);

		this.load();
		this.createUpdateListener();
    }

	onDictionariesUpdate(event) {
		if ( event.key == this.storage_name ) {
			// console.log('Storage.changed', event.key);
			// Через реактивный proxy, иначе Vue не увидит изменений словарей.
			( this._proxy ?? this ).load();
		}
	}

	createUpdateListener() {
		// console.log('DictionariesClass.createUpdateListener');		
		// RELOAD dictionaries when another local user has changed it or it was changed in another browser tab
		window.addEventListener('storage', this.onDictionariesUpdate);
	}

	removeUpdateListener() {
		// Remove the event listener for 'storage'
		window.removeEventListener('storage', this.onDictionariesUpdate);
	}

    load() {
        // console.log('[DictionariesClass.load]');
        let localItems = JSON.parse(localStorage.getItem(this.storage_name)) || {};
        // console.log('loaded', localItems);

        localItems = this.sanitize(localItems);

        this.mergeProperties(localItems)
        this.addAssociativeVariations(localItems)
        this.translate(localItems)
    }

    // Reconcile dictionary arrays in place instead of replacing them.
    // Keeps the existing array reference AND the existing row objects (matched
    // by id), so transient UI flags like row.selected and parent references
    // (current_category и т.п.) survive a dictionary refresh — иначе update()
    // сбрасывает выделение в таблицах.
    mergeProperties(newData = {}) {
        for (let key in newData) {
            const incoming = newData[key];
            const existing = this.getProperty(key);

            // Reconcile only arrays of objects with ids; всё остальное — заменяем.
            if ( Array.isArray(incoming) && Array.isArray(existing) ) {
                const byId = new Map(existing.map(item => [item?.id, item]));

                const merged = incoming.map(row => {
                    const prev = byId.get(row?.id);
                    if ( prev ) {
                        // Обновляем поля существующего объекта, сохраняя его
                        // ссылку и временные флаги (selected, is_selected).
                        Object.assign(prev, row);
                        return prev;
                    }
                    return row;
                });

                // Мутируем существующий массив, чтобы сохранить его ссылку.
                existing.splice(0, existing.length, ...merged);
            } else {
                this.setProperty(key, incoming);
            }
        }

        return true;
    }

    sanitize(items) {
        // console.log('[DictionariesClass.sanitize]');
        if ( typeof items !== 'object' || Array.isArray(items) ) {
            console.warn('[DictionariesClass.sanitize] Root value is not an object — clearing storage');
            localStorage.removeItem(this.storage_name);
            return {};
        }

        let cleaned = {};
        let hasBroken = false;

        Object.keys(items).forEach(key => {
            const val = items[key];
            if ( Array.isArray(val) || (typeof val === 'object' && val !== null) ) {
                cleaned[key] = val;
            } else {
                console.warn(`[DictionariesClass.sanitize] Dropping broken dict "${key}": expected array/object, got ${typeof val} — "${String(val).slice(0, 40)}"`);
                hasBroken = true;
            }
        });

        if ( hasBroken ) {
            console.warn('[DictionariesClass.sanitize] Broken entries removed — saving cleaned storage');
            localStorage.setItem(this.storage_name, JSON.stringify(cleaned));
        }

        return cleaned;
    }

    async update(dict_name) {
        // console.log('[DictionariesClass.update]', dict_name);
        let result = JSON.parse(localStorage.getItem(this.storage_name)) ?? {}

		let headers = dict_name ? {'X-only': dict_name} : {};

		const response = await this.apiClient.get( this.url, {}, headers, { disable_pause: true, timeout: UPDATE_TIMEOUT } )
        if ( response.error ) {
            Toast.error('Error while loading base dictionaries')
            console.warn(`[DictionariesStorageClass.update] Can't update local dictionaries store. ${response.error}`);
            return response;
        }

		// console.log('dict_response', response);

        // Полный ответ без единого справочника легитимным не бывает (системные
        // приходят всегда) — значит ответ подменён или обрезан, и затирать им
        // рабочие данные нельзя.
        if ( !dict_name && !Object.keys(response.data ?? {}).length ) {
            console.warn(`[DictionariesStorageClass.update] Empty dictionaries response — local store kept`);
            return { error: 'Empty dictionaries response' };
        }

        // Состав словарей задаётся заново только успешным полным ответом: до него
        // сохранённые данные не трогаем — в офлайне это единственный источник
        // списка кассиров, и потеря его превращает кассу в нерабочую.
        if ( !dict_name )
            result = {};

        Object.keys(response.data).forEach((key) => {
            if (!dict_name || key==dict_name) {
                const val = response.data[key];
                if ( !Array.isArray(val) && typeof val !== 'object' ) {
                    console.warn(`[DictionariesClass.update] Skipping dict "${key}": expected array or object, got ${typeof val}`);
                    return;
                }
                result[key] = val;
            }
        })

		// console.log('aaa', result);
		

        // Parse accounts settings
        // result.accounts.map(item=>{
        //     item.settings = $H.g.parse_json(item.settings);
        //     return item
        // })

		this.removeUpdateListener()
        localStorage.setItem(this.storage_name, JSON.stringify(result))      
		this.createUpdateListener()
        // console.log('updated', result);
        
        // super.setProperties(result)
        // this.addAssociativeVariations(result);
        // this.translate(result)

        this.load()

        this.isUpdated = true;
        console.msg(`[+] Dictionaries updated`);

        return true;		
    }
    
    save(dict_name) {
        let result = JSON.parse(localStorage.getItem(this.storage_name)) ?? {}

        result[dict_name] = super.getProperty(dict_name);

        localStorage.setItem(this.storage_name, JSON.stringify(result)) 
    }

    clear() {
        localStorage.setItem(this.storage_name, JSON.stringify({}))
    }

    addAssociativeVariations(original) {
        // console.log('addAssociativeVariations', original);
        if ( !original )
            return false;

        Object.keys(original).forEach(key => {
            const val = original[key];
            if ( !Array.isArray(val) && typeof val !== 'object' ) {
                console.warn(`[DictionariesClass] Skipping assoc "${key}": not an array/object`);
                return;
            }
            super.setProperty(key+'_a', val ? $H.Ar.toAssociative(val, 'id') : {} );
        })
    }

    translate(dicts) {
        // console.log('translate dict', dicts, typeof dicts);
		if ( typeof dicts !== 'object' ) {
			Toast.error('[DictionaryClass.translate] dicts object is not object')
			return false
		}

        Object.keys(dicts).forEach(key => {
			// console.log('vv', key, typeof dicts[key]);
			if ( !Array.isArray(dicts[key]) && typeof dicts[key] !== 'object' ) {
				Toast.error('[DictionaryClass.translate] dict "'+key+'" is not array/object')
				return
			}
            let dict = Object.assign({}, dicts[key]);
            
            Object.keys(dict).forEach(prop_key => {
                dict[prop_key].name = dict[prop_key].name ? $t(dict[prop_key].name) : ''
            })

            super.setProperty(key+'_t', dict);
        })
    }   
}

export { DictionariesClass }