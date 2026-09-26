import { General }    from './helpers/General.js'
import { Arrays }     from './helpers/Arrays.js'
import { Datetime }   from './helpers/Datetime.js'
import { Strings }    from './helpers/Strings.js'
import { Numbers }    from './helpers/Numbers.js'
import { System }     from './helpers/System.js'
import { Validation } from './helpers/Validation.js'
import { Html }       from './helpers/Html.js'
import { Vue }        from './helpers/Vue.js'

const Helpers = {
    G: General,
    g: General,
    Ar: Arrays,
    ar: Arrays,
    Dt: Datetime,
    dt: Datetime,
    Str: Strings,
    str: Strings,
    Num: Numbers,
    num: Numbers,
    Sys: System,
    sys: System,
    Validate: Validation,
    validate: Validation,
    Html: Html,
    html: Html,
    Vue: Vue,
    vue: Vue,

    install(app) {
        app.config.globalProperties.$h = this;
        app.config.globalProperties.$H = this;
    }
}

// Часть перенесённых компонентов зовёт помощники из обычной области видимости,
// а не через экземпляр компонента. Набор хоста с тем же именем — приоритетнее:
// хост, у которого свой набор, пользуется им без изменений.
if (typeof window !== 'undefined' && ! window.$H) {
    window.$H = Helpers;
}

export default Helpers;
