import { Arrays }   from './helpers/Arrays.js'
import { Datetime } from './helpers/Datetime.js'

const Helpers = {
    Ar: Arrays,
    ar: Arrays,
    Dt: Datetime,
    dt: Datetime,

    install(app) {
        app.config.globalProperties.$h = this;
        app.config.globalProperties.$H = this;
    }
}

// Часть перенесённых компонентов зовёт помощники из обычной области видимости,
// а не через экземпляр компонента. Набор хоста с тем же именем — приоритетнее:
// он шире, а перенесённый код им пользуется без изменений.
if (typeof window !== 'undefined' && ! window.$H) {
    window.$H = Helpers;
}

export default Helpers;
