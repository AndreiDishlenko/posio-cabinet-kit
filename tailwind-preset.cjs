// Динамическую высоту вьюпорта Apple понимает только с 15.4; ниже вся декларация
// отбрасывается целиком, и элемент остаётся без высоты — вместе с ним схлопывается
// вся цепочка потомков на процентной высоте. Поэтому статическая единица идёт
// первой строкой, динамическая перекрывает её там, где понята.
//
// Обычная функция, а не обёртка сборщика: пресет читается из каталога зависимостей
// хоста, и лишний require оттуда — лишний повод сломаться на нестандартной раскладке.
const dynamicViewportHeights = function ({ addUtilities }) {
    const steps      = [55, 75, 80, 90, 95, 100];
    const properties = { 'h': 'height', 'min-h': 'minHeight', 'max-h': 'maxHeight' };

    const utilities = {};

    for (const step of steps)
        for (const [prefix, property] of Object.entries(properties))
            utilities[`.${prefix}-dvh-${step}`] = { [property]: [`${step}vh`, `${step}dvh`] };

    addUtilities(utilities);
};

// Отступ между элементами внутри flex Apple понимает только с 14.1: ниже объявление
// отбрасывается целиком и вёрстка слипается. Признак ставится замером в разметке
// (в сетке то же свойство работает с 12-й версии, поэтому @supports его не различает) —
// скрипт-замер живёт в app.blade.php пакета, здесь только margin-фолбэк под его признак.
//
// Утилиты `gap-*` ядра здесь не переопределяются: у оригинала это требовало
// `corePlugins.gap: false` в конфиге хоста, а установщик пакета эту настройку нигде
// не проставляет — включить её молча значило бы задвоить регистрацию `gap` у любого
// хоста без предупреждения. `wrap-gap-*` с ядром не пересекается по имени и не требует
// такой правки — фолбэк работает у любого хоста сразу после подключения пресета.
const flexGapFallback = function ({ matchUtilities, theme }) {
    // Ряды с переносом: отступ соседям здесь не подходит — первый элемент новой
    // строки получил бы лишний отступ, а между строками отступа не было бы.
    // Поэтому отступ раздаётся всем элементам, а лишняя внешняя рамка снимается
    // отрицательным отступом контейнера. Из-за этого применимо только к
    // контейнерам без собственных полей и без собственных внешних отступов.
    const wrapRules = (sides) => (value) => Object.fromEntries([
        [`html.no-flex-gap &`, Object.fromEntries(sides.map(s => [`margin${s}`, `calc(${value} / -2)`]))],
        [`html.no-flex-gap & > *`, Object.fromEntries(sides.map(s => [`margin${s}`, `calc(${value} / 2)`]))],
    ]);

    matchUtilities(
        {
            'wrap-gap': value => ({
                gap: value,
                ...wrapRules(['Top', 'Right', 'Bottom', 'Left'])(value),
            }),
            'wrap-gap-x': value => ({
                columnGap: value,
                ...wrapRules(['Right', 'Left'])(value),
            }),
            'wrap-gap-y': value => ({
                rowGap: value,
                ...wrapRules(['Top', 'Bottom'])(value),
            }),
        },
        { values: theme('gap'), type: ['length', 'any'] }
    );
};

module.exports = {
    // Запасной путь, а не основной: Tailwind v3 не сливает `content` пресета с
    // хостовым — при наличии `content` у хоста этот список не применяется вовсе.
    // Рабочий glob живёт в конфиге хоста и поддерживается установщиком
    // (`cabinet-kit:install`) и командой обновления (`cabinet-kit:sync-config`);
    // менять его здесь — значит менять и константу в этих командах.
    content: [
        './vendor/posio/cabinet-kit/resources/**/*.{vue,js,ts}',
    ],

    // Тема кабинета переключается признаком на корне документа, а не системной
    // настройкой: тёмная включается сразу при старте приложения.
    darkMode: 'class',

    theme: {
        extend: {
            screens: {
                xs: '480px',
                '2xl': '1536px',
                'lt-xs': { max: '479px' },
                'lt-sm': { max: '639px' },
                'lt-md': { max: '767px' },
                'lt-lg': { max: '1023px' },
                'lt-xl': { max: '1279px' },
                'lt-2xl': { max: '1535px' },
            },
            // Размеры шрифта в шаблонах — те же переменные, что и в стилях кабинета:
            // одна правка меняет и утилитарные классы, и рукописные объявления.
            // Ступень `md` в наборе сборщика отсутствует — без неё шаблоны пакета,
            // которые её используют, молча остаются с унаследованным размером.
            fontSize: {
                xxs: 'var(--text-xxs)',
                xs: 'var(--text-xs)',
                sm: 'var(--text-sm)',
                md: 'var(--text-md)',
                base: 'var(--text-base)',
                lg: 'var(--text-lg)',
                xl: 'var(--text-xl)',
                xxl: 'var(--text-2xl)',
                '2xl': 'var(--text-2xl)',
                '3xl': 'var(--text-3xl)',
                '4xl': 'var(--text-4xl)',
                '5xl': 'var(--text-5xl)',
            },
            gridTemplateRows: {
                'auto-fr': 'auto 1fr',
                'header-fr': 'theme(spacing.10) 1fr',
            },
        },
    },

    plugins: [dynamicViewportHeights, flexGapFallback],
};
