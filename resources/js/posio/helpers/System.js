// Единая точка правды о платформе и возможностях движка. Компоненты не должны
// повторять разбор user-agent и гадать, что умеет браузер: где возможно —
// проверяется наличие самого API, user-agent остаётся только там, где признака
// в объектной модели нет (инструкция по установке, отличие Safari от Chromium).

const userAgent = () => (typeof navigator !== 'undefined' && navigator.userAgent) || '';

// navigator.platform устарел, но это единственный признак, доступный вплоть до
// нижней планки поддержки; современное поле берём первым, если оно есть.
const platform = () => {
    if (typeof navigator === 'undefined')
        return '';

    return (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
};

const touchPoints = () => (typeof navigator !== 'undefined' && navigator.maxTouchPoints) || 0;

export const System = {
    // Планшет Apple с 13-й системы представляется десктопным Mac — отличаем по
    // наличию мультитача, иначе он попадает во все десктопные ветки.
    isIos: function() {
        if (typeof navigator === 'undefined')
            return false;

        if (/iPad|iPhone|iPod/.test(userAgent()) && !window.MSStream)
            return true;

        return /Mac/i.test(platform()) && touchPoints() > 1;
    },

    isMacOS: function() {
        return /Mac/i.test(platform()) && !this.isIos();
    },

    isApple: function() {
        return this.isIos() || this.isMacOS();
    },

    // Настоящий Safari, а не Chromium/Firefox с их «Safari» в хвосте строки.
    isSafari: function() {
        const ua = userAgent();

        return /Safari/i.test(ua) && !/Chrome|Chromium|CriOS|FxiOS|EdgiOS|OPiOS|Edg\//i.test(ua);
    },

    // На iOS любой браузер — это оболочка над системным WebKit, поэтому набор
    // возможностей там одинаков независимо от названия.
    isWebKit: function() {
        return this.isIos() || this.isSafari();
    },

    // Мажорная версия Safari; 0, если браузер не Safari или версию не назвал.
    safariVersion: function() {
        if (!this.isSafari())
            return 0;

        const match = userAgent().match(/Version\/(\d+)/);

        return match ? Number(match[1]) : 0;
    },

    // ── Возможности устройства ──────────────────────────────────────────────

    supportsWebUsb: function() {
        return typeof navigator !== 'undefined' && !!navigator.usb;
    },

    supportsWebBluetooth: function() {
        return typeof navigator !== 'undefined' && !!(navigator.bluetooth && navigator.bluetooth.requestDevice);
    },

    // Сенсорный ввод как основной — планшет кассы, телефон; мышь с тачпадом сюда не попадает.
    isTouchPrimary: function() {
        if (typeof window === 'undefined' || !window.matchMedia)
            return touchPoints() > 0;

        return window.matchMedia('(pointer: coarse)').matches;
    },

    // ── Установка как приложение (PWA) ──────────────────────────────────────

    // Приложение уже запущено из своего окна/с домашнего экрана.
    isStandalone: function() {
        if (typeof window === 'undefined')
            return false;

        if (navigator.standalone)
            return true;

        if (!window.matchMedia)
            return false;

        return ['standalone', 'fullscreen', 'minimal-ui']
            .some(mode => window.matchMedia(`(display-mode: ${mode})`).matches);
    },

    // Браузер умеет предлагать установку сам (beforeinstallprompt) — это Chromium.
    supportsInstallPrompt: function() {
        return typeof window !== 'undefined' && 'onbeforeinstallprompt' in window;
    },

    // Установка возможна, но только руками пользователя: Safari на iOS
    // («Поділитися» → «На екран Домівка») и Safari на macOS («Додати до Dock»,
    // появилось в 17-й версии — раньше добавлять на десктопе было нечем).
    supportsManualInstall: function() {
        if (this.supportsInstallPrompt())
            return false;

        if (this.isIos())
            return this.isSafari();

        return this.isMacOS() && this.safariVersion() >= 17;
    },
}
