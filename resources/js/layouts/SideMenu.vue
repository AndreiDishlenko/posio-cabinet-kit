<template>

    <!--
        Gemini-style side menu.
          • свёрнутый рельс (rail) — только иконки, ширина --gm-rail-width;
          • клик по бренду/кнопке-панели → закреплённый разворот, который
            ТОЛКАЕТ контент (layout reflow); состояние в localStorage;
          • на мобильном (<1024px) — выезжающая слева панель (pullout).

        Структура: shell резервирует место в потоке (rail | expanded), panel —
        absolute-панель внутри shell.
    -->
    <div class="gm-shell"
        :class="{
            'is-pinned':   !isFolded || forceExpand,
            'is-pullout':  isPullout,
            'is-expanded': isExpanded,
            'is-disabled': disabled,
        }"
        >

        <!-- Выезд панели (transform) и её прокрутка намеренно разнесены по двум
             элементам: совмещение постоянного трансформа со скроллящимся
             контейнером заставляет мобильный Chrome держать всё содержимое в
             одном GPU-слое и перерастеризовывать его на каждом кадре — на
             слабых Android-планшетах векторная графика при этом мигает и
             пропадает. Внешний элемент только едет, внутренний только скроллит. -->
        <div ref="sideMenu" class="gm-panel">
        <div class="gm-panel-scroll no-scrollbar">

            <!-- Header: бренд + переключатель панели.
                 Знак (symbol) и лого (logo) наложены и кроссфейдятся по opacity,
                 закреплены за один и тот же левый край → знак не «прыгает».
                 Кнопка-панель всегда в DOM, появляется по opacity. -->
            <div class="gm-header">
                <button type="button" class="gm-brand"
                    :aria-label="$t('Main menu')"
                    @click="onBurger"
                    >
                    <img src="/cabinet-assets/images/cabinet_symbol_dark_theme.svg?v=3"  class="gm-brand-img gm-brand-symbol gm-brand-theme-dark"  alt="Posio"/>
                    <img src="/cabinet-assets/images/cabinet_logo_dark_theme.svg?v=3"    class="gm-brand-img gm-brand-logo   gm-brand-theme-dark"  alt="Posio"/>
                    <img src="/cabinet-assets/images/cabinet_symbol_light_theme.svg?v=3" class="gm-brand-img gm-brand-symbol gm-brand-theme-light" alt="Posio"/>
                    <img src="/cabinet-assets/images/cabinet_logo_light_theme.svg?v=3"   class="gm-brand-img gm-brand-logo   gm-brand-theme-light" alt="Posio"/>
                </button>

                <button type="button" class="gm-toggle"
                    :aria-label="$t('Collapse menu')"
                    @click="onBurger"
                    >
                    <Icon icon="material-symbols:left-panel-close-outline-rounded" class="gm-icon"/>
                </button>
            </div>

            <!-- Навигация: группы кабинета в виде секций Gemini -->
            <nav class="gm-nav">
                <div v-for="(group, groupIndex) in in_data" :key="groupIndex" class="gm-group">

                    <!-- Заголовок-дропдаун: клик сворачивает/разворачивает группу.
                         Всегда в DOM (постоянная высота) → нет вертикального прыжка;
                         в рельсе скрыт по opacity и не кликабелен (пункты видны всегда). -->
                    <button type="button" class="gm-group-label"
                        :aria-expanded="isGroupExpanded(groupIndex)"
                        @click="toggleGroup(groupIndex)"
                        >
                        <span class="gm-group-label-text">{{ $t(group.label) }}</span>
                        <Icon icon="ep:arrow-down-bold" class="gm-group-arrow"
                            :class="{ 'is-open': isGroupExpanded(groupIndex) }"/>
                    </button>

                    <transition name="gm-collapse">
                        <ul v-if="group.children && isGroupExpanded(groupIndex)" class="gm-group-list">
                            <li v-for="item in group.children"
                                :key="item.id ?? (item.route ?? item.link)"
                                :id="`menu-${item.route?.replaceAll('.', '-') ?? item.link}`"
                                class="gm-item"
                                :class="{
                                    'is-active':   item.id == current_id,
                                    'is-disabled': disabled || (!item.route && !item.link),
                                }"
                                >

                                <!-- Inertia-навигация -->
                                <Link v-if="item.route" class="gm-link"
                                    :href="route(item.route)"
                                    :prefetch="['mount', 'hover']"
                                    >
                                    <Icon :icon="item.icon" class="gm-icon"/>
                                    <span class="gm-label">{{ $t(item.label) }}</span>
                                </Link>

                                <!-- Не-Inertia назначения (log-viewer и т.п.) — обычный <a>:
                                     Inertia перехватывает <Link> как SPA-визит, а страница
                                     без X-Inertia ответа рендерится в iframe-модалке. -->
                                <a v-else-if="item.link" class="gm-link" :href="item.link">
                                    <Icon :icon="item.icon" class="gm-icon"/>
                                    <span class="gm-label">{{ $t(item.label) }}</span>
                                </a>

                                <span v-else class="gm-link">
                                    <Icon :icon="item.icon" class="gm-icon"/>
                                    <span class="gm-label">{{ $t(item.label) }}</span>
                                </span>

                            </li>
                        </ul>
                    </transition>

                </div>
            </nav>

            <!-- Footer (как у Gemini): пользователь → профиль, шестерёнка → меню
                 табов настроек. Прижат к низу панели. -->
            <div class="gm-footer">

                <!-- Пользователь → страница настроек, таб «User profile» -->
                <Link class="gm-link gm-footer-user"
                    :href="profileHref"
                    :prefetch="['mount', 'hover']"
                    :title="userName"
                    >
                    <Avatar :src="userAvatar" :user_name="userName" size="26px" class="gm-footer-avatar"/>
                    <span class="gm-label">{{ userName }}</span>
                </Link>

                <!-- Настройки → выпадающее меню с табами страницы настроек.
                     Список берётся из общего settingsTabs.js → совпадает со
                     страницей CabinetSettings автоматически. -->
                <Dropdown class="gm-footer-settings"
                    align="left"
                    direction="up"
                    transition="menu"
                    :downOnClick="true"
                    :offset="6"
                    :area_zindex="2001"
                    buttonclass="gm-footer-settings-button"
                    dropareaclass="gm-footer-settings-menu p-1"
                    >
                    <template #button>
                        <span class="gm-footer-settings-trigger" :title="$t('Settings')">
                            <Icon icon="material-symbols:settings-outline-rounded" class="gm-icon"/>
                        </span>
                    </template>

                    <template #dropdownitems>
                        <Link v-for="tab in settingsTabs"
                            :key="tab.id"
                            class="gm-footer-menu-item"
                            :href="settingsHref(tab.id)"
                            :prefetch="['mount', 'hover']"
                            >
                            {{ $t(tab.label) }}
                        </Link>
                    </template>
                </Dropdown>

            </div>

        </div>
        </div>

    </div>

</template>

<script>
    import { Link }         from '@inertiajs/vue3';
    import { Icon }         from '@iconify/vue';

    import Avatar           from '@/js/Elements/Avatar.vue';
    import Dropdown         from '@/js/Elements/Dropdown.vue';

    import { buildSettingsTabs } from '../pages/Settings/settingsTabs.js';

    export default {
        components: { Link, Icon, Avatar, Dropdown },
        props: {
            in_data: {
                type: Array,
                default: () => [],
            },
            current_id: {
                type: [Number, String],
                default: null,
            },
            disabled: {
                type: Boolean,
                default: false
            },
            activeAccountId: {
                type: [Number, String],
                default: null,
            },
        },
        data() {
            return {
                // Свёрнуто по умолчанию (как Gemini). Восстанавливается из localStorage в created().
                isFolded: true,
                isPullout: false,           // мобильный выезд
                forceExpand: false,
                // Группы развёрнуты по умолчанию — храним только те, что пользователь свернул явно.
                collapsedGroups: [],
            }
        },
        computed: {
            // Показывать подписи пунктов (панель развёрнута любым способом)
            isExpanded() {
                return !this.isFolded || this.isPullout || this.forceExpand;
            },
            // Группа, в которой лежит активный пункт — она всегда развёрнута
            activeGroupIndex() {
                if ( !this.current_id ) return -1;
                return this.in_data.findIndex(group =>
                    group.children?.some(item => item.id == this.current_id)
                );
            },
            // ── Footer: пользователь + настройки ───────────────────────────
            userName() {
                return this.$page.props.user?.name || this.$t('Guest');
            },
            userAvatar() {
                return this.$page.props.user?.avatar;
            },
            // Табы настроек — тот же состав, что и на странице CabinetSettings
            // (общий settingsTabs.js). Право manage-members шарится глобально.
            settingsTabs() {
                return buildSettingsTabs(this.$page.props.user?.can_manage_account);
            },
            // Клик по пользователю ведёт именно в профиль (таб settings),
            // поэтому таб задаётся явно через query — иначе восстановится
            // последний открытый.
            profileHref() {
                return this.settingsHref('profile');
            },
        },
        watch: {
            activeAccountId() {
                this.restoreCollapsedGroups();
            },
        },
        mounted() {
            document.addEventListener('click', this.handleClickOutside);
            window.addEventListener('resize', this.updateReservedWidth);
            this.$emitter.on('burger_button_click', this.onBurger);
            this.$emitter.on('burger_menu_opened',  this.closeSideMenu);
            this.$emitter.on('open_side_menu',       this.openSideMenu);
            this.$emitter.on('close_side_menu',      this.closeSideMenu);
            this.$emitter.on('tour_show_all_groups', this.tourShowAll);
            this.$emitter.on('tour_restore_groups',  this.tourRestore);
        },
        created() {
            // Инициализируем состояние сворачивания ДО первого рендера, чтобы при
            // перемонтировании меню (Inertia пересоздаёт layout на каждой навигации)
            // не было «дёрганья» анимации.
            if (!this.disabled) {
                const saved = localStorage.getItem('sideMenuState');
                if (saved === 'false') this.isFolded = false; // пользователь закрепил открытым
                if (saved === 'true')  this.isFolded = true;
            }

            this.restoreCollapsedGroups();
            this.dropLegacyGroupsSetting();
            this.updateReservedWidth();
        },
        beforeUnmount() {
            document.removeEventListener('click', this.handleClickOutside);
            window.removeEventListener('resize', this.updateReservedWidth);
            this.$emitter.off('burger_button_click', this.onBurger);
            this.$emitter.off('burger_menu_opened',  this.closeSideMenu);
            this.$emitter.off('open_side_menu',       this.openSideMenu);
            this.$emitter.off('close_side_menu',      this.closeSideMenu);
            this.$emitter.off('tour_show_all_groups', this.tourShowAll);
            this.$emitter.off('tour_restore_groups',  this.tourRestore);
        },
        methods: {
            // Единый обработчик «бургера» (в меню и в шапке): десктоп — закрепление,
            // мобильный — выезд/скрытие.
            onBurger() {
                // SideMenu.onBurger
                if (this.disabled)
                    return false;

                if (window.innerWidth < 1024) {
                    this.isPullout = !this.isPullout;
                    if (this.isPullout)
                        this.$emitter.emit('close_burger_menu');
                    return true;
                }

                this.togglePinned();
                if (!this.isFolded)
                    this.$emitter.emit('close_burger_menu');
                return true;
            },
            togglePinned() {
                // SideMenu.togglePinned
                this.isFolded = !this.isFolded;
                localStorage.setItem('sideMenuState', this.isFolded);
                this.updateReservedWidth();
            },
            // Ширина, реально занятая меню на десктопе — читается модалками (см.
            // ModalForm), которые всплывают через teleport в <body> и поэтому не
            // могут узнать это из обычного flex-layout. На мобильном меню не
            // толкает контент (выезжает поверх), поэтому там всегда 0.
            updateReservedWidth() {
                const width = window.innerWidth < 1024
                    ? '0px'
                    : (this.isFolded ? 'var(--gm-rail-width)' : 'var(--gm-expanded-width)');
                document.documentElement.style.setProperty('--cabinet-menu-width', width);
            },
            openSideMenu() {
                // SideMenu.openSideMenu — выезд на мобильном (используется туром)
                if (this.disabled)
                    return false;
                if (window.innerWidth < 1024) {
                    this.isPullout = true;
                    this.$emitter.emit('close_burger_menu');
                }
                return true;
            },
            closeSideMenu() {
                // SideMenu.closeSideMenu
                this.isPullout = false;
                this.forceExpand = false;
            },
            handleClickOutside(event) {
                // SideMenu.handleClickOutside — закрытие мобильного выезда по клику вне
                if (!this.isPullout)
                    return false;
                if (event.target.closest('.burger-button'))
                    return false;
                if (event.target.closest('.spo-hint'))
                    return false;
                if (this.$refs.sideMenu && !this.$refs.sideMenu.contains(event.target))
                    this.isPullout = false;
            },
            showAllGroups() {
                this.forceExpand = true;
            },
            restoreGroups() {
                this.forceExpand = false;
            },

            // Ссылка на страницу настроек с активным табом в query. Ключ query —
            // storage-key группы табов на странице ('settings'), см. Tabs.vue →
            // applyQueryTab(). Ziggy кладёт неизвестный маршруту параметр в query.
            settingsHref(tabId) {
                return route('cabinet.settings', { tab: tabId });
            },

            // ── Группы-дропдауны ───────────────────────────────────────────
            toggleGroup(groupIndex) {
                // SideMenu.toggleGroup — сворачивание/разворачивание группы.
                // В рельсе (панель свёрнута) пункты видны всегда — тогло не нужно.
                if ( !this.isExpanded )
                    return;

                const index = this.collapsedGroups.indexOf(groupIndex);
                if (index >= 0)
                    this.collapsedGroups.splice(index, 1);
                else
                    this.collapsedGroups.push(groupIndex);

                this.saveCollapsedGroups();
            },
            isGroupExpanded(groupIndex) {
                // SideMenu.isGroupExpanded
                if ( this.forceExpand )                return true;
                if ( !this.isExpanded )                return true;  // рельс: все пункты видны
                if ( groupIndex === this.activeGroupIndex ) return true;  // активная группа
                return !this.collapsedGroups.includes(groupIndex);
            },
            saveCollapsedGroups() {
                // SideMenu.saveCollapsedGroups — состояние в $settings (per-account)
                if ( !this.$settings ) return;

                const accountId = String(this.activeAccountId ?? 'default');
                const menuGroups = this.$settings.getSetting('menu_groups_collapsed', {}) || {};
                menuGroups[accountId] = this.collapsedGroups;
                this.$settings.setSetting('menu_groups_collapsed', menuGroups);
            },
            // Разделы меню раньше запоминались наоборот — списком развёрнутых. После
            // смены умолчания на «развёрнуто» те данные не читаются и лишь занимают место.
            dropLegacyGroupsSetting() {
                // SideMenu.dropLegacyGroupsSetting
                if ( !this.$settings ) return;
                if ( !this.$settings.getSetting('menu_groups', null) ) return;

                this.$settings.removeItem('menu_groups');
            },
            restoreCollapsedGroups() {
                // SideMenu.restoreCollapsedGroups
                if ( !this.$settings ) {
                    this.collapsedGroups = [];
                    return;
                }

                const accountId = String(this.activeAccountId ?? 'default');
                const menuGroups = this.$settings.getSetting('menu_groups_collapsed', {});
                const savedGroups = menuGroups?.[accountId];

                this.collapsedGroups = Array.isArray(savedGroups)
                    ? savedGroups.filter(group => Number.isInteger(group) && group >= 0)
                    : [];
            },
        }
    }
</script>

<style lang="scss" scoped>

    /* ── Shell: резервирует место в потоке (rail | expanded) ───────────── */
    .gm-shell {
        position: relative;
        flex-shrink: 0;
        height: 100%;
        width: var(--gm-rail-width);
        // z-index задаёт stacking context для всего меню целиком: дочерний
        // z-index панели (см. .gm-panel в мобильной media query) действует
        // только внутри этого контекста и наружу не «пробивается». Поэтому
        // значение должно быть выше любого z-index контента страницы (липкие
        // заголовки таблиц, дропдауны и т.п.), иначе меню окажется под ними,
        // несмотря на собственный больший z-index панели.
        z-index: 2000;
        transition: width var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-shell.is-pinned {
        width: var(--gm-expanded-width);   // закреплён открытым → толкает контент
    }

    @media (max-width: 1023.98px) {
        .gm-shell,
        .gm-shell.is-pinned {
            width: 0;                       // на мобильном меню не резервирует место
        }
    }

    /* ── Panel: absolute-оверлей внутри shell ──────────────────────────── */
    // Только позиция, фон и выезд — прокрутка вынесена во вложенный элемент
    // (см. комментарий в шаблоне): скролл поверх постоянного трансформа даёт
    // артефакты растеризации графики на слабых мобильных GPU.
    .gm-panel {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: var(--gm-rail-width);

        background: var(--gm-sidemenu-bg);
        color: var(--gm-item-color);
        font-family: var(--gm-font);

        box-sizing: border-box;

        transition:
            width      var(--gm-ease-dur) var(--gm-ease),
            transform  var(--gm-ease-dur) var(--gm-ease),
            box-shadow var(--gm-ease-dur) var(--gm-ease);
    }

    // Горизонтально — именно clip, а не hidden: hidden делает бокс прокручиваемым
    // контейнером и по этой оси тоже, а прокручиваемый контейнер мобильный Chrome
    // выносит в отдельный композитный слой. На слабых Android-GPU растр этого слоя
    // после анимации не восстанавливается — векторная графика внутри пропадает.
    // clip режет вылезающее без семантики прокрутки, слоя не возникает.
    .gm-panel-scroll {
        height: 100%;
        width: 100%;

        display: flex;
        flex-direction: column;

        overflow-x: hidden;             // фолбэк для движков без clip
        overflow-x: clip;
        overflow-y: auto;
        box-sizing: border-box;
    }

    // Закреплён открытым — панель заполняет расширенный shell (контент сдвинут)
    .gm-shell.is-pinned .gm-panel {
        width: var(--gm-expanded-width);
    }

    @media (max-width: 1023.98px) {
        .gm-panel {
            width: var(--gm-expanded-width);
            transform: translateX(-100%);   // спрятана слева
            z-index: 10000;
        }
        .gm-shell.is-pullout .gm-panel {
            transform: translateX(0);
            box-shadow: var(--gm-overlay-shadow);
        }
    }

    /* ── Header (бренд + переключатель) ────────────────────────────────── */
    .gm-header {
        position: relative;             // база для кнопки-переключателя (см. ниже)
        height: var(--header-height);
        display: flex;
        align-items: center;
        padding-left: var(--gm-panel-pad);
        padding-right: var(--gm-panel-pad);
        flex-shrink: 0;
    }

    // Бренд: symbol (знак) и logo (знак + «POSIO») наложены и кроссфейдятся.
    // В logo.svg знак — та же графика в том же масштабе, что и в symbol.svg
    // (обе с viewBox высотой 5000, знак = левые 3500 единиц). Поэтому обе картинки
    // рендерим ОДНОЙ высотой и с ОДНИМ левым краем → знак остаётся НЕПОДВИЖНЫМ,
    // при развороте проявляется только текст «POSIO» справа. Разные высоты как раз
    // и сдвигали/масштабировали знак при открытии/закрытии.
    .gm-brand {
        position: relative;
        flex: 0 0 auto;
        width: 40px;                    // стабильный клик-таргет (текст лого выходит за пределы)
        height: var(--gm-item-height);
        padding: 0;
        border: 0;
        background: transparent;
        cursor: pointer;
        outline: none;                  // без .button — рамка фокуса не снята UA-стилями по умолчанию
    }

    .gm-brand-img {
        position: absolute;
        top: 50%;
        // Знак уже иконок (≈14px при height 20), поэтому симметричный отступ
        // (40−14)/2 ≈ 13px ставит его центр на 28px = центр рельса и колонки иконок.
        left: 7px;
        transform: translateY(-50%);
        height: 26px!important;                   // ОБЩАЯ высота знака в обоих состояниях (тюнится здесь)
        width: auto;
        max-width: none;                // снять Tailwind Preflight img { max-width: 100% }
        transition: opacity var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-brand-symbol { height: 20px; opacity: 1; }    // рельс: виден знак
    .gm-brand-logo   { height: 26px; opacity: 0; }    // рельс: скрыт полный лого

    .gm-shell.is-expanded .gm-brand-symbol { opacity: 0; }
    .gm-shell.is-expanded .gm-brand-logo   { opacity: 1; }

    // Тема переключает не opacity (кроссфейд — только между знаком и лого), а
    // видимость целиком: на светлой теме тёмный вариант скрыт, показан светлый.
    .gm-brand-theme-light                 { display: none; }
    html.light .gm-brand-theme-dark       { display: none; }
    html.light .gm-brand-theme-light      { display: block; }

    // Кнопка-панель всегда в DOM (появляется по opacity). Выведена из потока:
    // в свёрнутом рельсе она вместе со знаком бренда не помещается по ширине и
    // раздувала область переполнения панели, из-за чего та становилась
    // прокручиваемой по горизонтали (см. комментарий у прокручиваемого элемента).
    // Прижата к правому краю шапки — в развёрнутом состоянии результат тот же,
    // что и от auto-отступа, но на раскладку она больше не влияет.
    .gm-toggle {
        position: absolute;
        right: var(--gm-panel-pad);
        top: 0;
        bottom: 0;
        margin-top: auto;               // вертикальное центрирование без transform
        margin-bottom: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: 0;
        background: transparent;
        cursor: pointer;
        outline: none;                  // без .button — рамка фокуса не снята UA-стилями по умолчанию
        border-radius: var(--gm-item-radius);
        color: var(--gm-item-color);
        opacity: 0;
        pointer-events: none;
        transition: background-color var(--gm-ease-dur) var(--gm-ease),
                    opacity          var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-shell.is-expanded .gm-toggle {
        opacity: 1;
        pointer-events: auto;
    }

    .gm-toggle:hover {
        background-color: var(--gm-item-hover-bg);
    }

    /* ── Навигация ─────────────────────────────────────────────────────── */
    .gm-nav {
        padding: 4px var(--gm-panel-pad) 12px;
        flex: 1 1 auto;
    }

    .gm-group {
        margin-top: 8px;
    }

    // Заголовок-дропдаун. Постоянная высота (текст — только opacity) → нет
    // вертикального прыжка. Выровнен по иконкам пунктов (левый край).
    // В рельсе скрыт (opacity 0) и не кликабелен — пункты видны всегда.
    .gm-group-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        @include flex-gap(8px);
        width: 100%;
        padding: 6px 10px;
        margin: 12px 0 4px;
        font-size: 12px;
        line-height: 1.4;
        font-weight: 500;
        letter-spacing: .2px;
        color: var(--gm-group-label-color);
        text-align: start;
        background: transparent;
        border: 0;
        border-radius: var(--gm-item-radius);
        cursor: pointer;
        outline: none;                  // без .button — рамка фокуса не снята UA-стилями по умолчанию
        opacity: 0;
        pointer-events: none;
        transition: opacity          var(--gm-ease-dur) var(--gm-ease),
                    background-color  var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-shell.is-expanded .gm-group-label {
        opacity: 1;
        pointer-events: auto;
    }

    .gm-group-label:hover {
        background-color: var(--gm-item-hover-bg);
    }

    .gm-group-label-text {
        white-space: nowrap;
        overflow: hidden;                 // фолбэк для движков без clip
        overflow: clip;                   // clip, а не hidden — без семантики прокрутки
        text-overflow: ellipsis;
    }

    .gm-group-arrow {
        width: 12px;
        height: 12px;
        flex: 0 0 auto;
        transition: transform var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-group-arrow.is-open {
        transform: rotate(180deg);
    }

    /* Разворот/сворачивание списка группы */
    .gm-collapse-enter-active,
    .gm-collapse-leave-active {
        overflow: hidden;
        transition: max-height var(--gm-ease-dur) var(--gm-ease),
                    opacity    var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-collapse-enter-from,
    .gm-collapse-leave-to {
        max-height: 0;
        opacity: 0;
    }

    .gm-collapse-enter-to,
    .gm-collapse-leave-from {
        max-height: 600px;
        opacity: 1;
    }

    .gm-group-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        @include flex-gap(2px, column);
    }

    .gm-item {
        list-style: none;
    }

    /* Пункт — pill. Внутренняя раскладка ИДЕНТИЧНА в обоих состояниях: иконка
       всегда слева (центр 28px = центр рельса), подпись — сразу за ней.
       Разворот полностью управляется анимацией ширины панели: ширина .gm-link
       (100%) растёт вместе с панелью → pill плавно превращается из круга в
       полосу, а подпись проявляется тем же ростом ширины + opacity. Никаких
       собственных width/justify/max-width кейфреймов → ничего не прыгает. */
    .gm-link {
        display: flex;
        align-items: center;
        height: var(--gm-item-height);
        width: 100%;
        border-radius: var(--gm-item-radius);
        color: var(--gm-item-color);
        text-decoration: none;
        box-sizing: border-box;
        cursor: pointer;
        // Клип подписи делает она сама (overflow + ellipsis), а по краям панели —
        // прокручиваемый контейнер. Клип скруглением здесь только заставлял
        // браузер строить маску вокруг каждой иконки — лишний слой на мобильном.
        padding-left: 10px;               // рельс: иконка 20px + 10px по бокам = центр 28px
        padding-right: 10px;

        transition: background-color var(--gm-ease-dur) var(--gm-ease),
                    color            var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-icon {
        width: 20px;
        height: 20px;
        flex: 0 0 auto;
        color: inherit;
    }

    // В рельсе подпись убрана из потока целиком: схлопнуть её до нуля мешал
    // неуменьшаемый левый отступ, и каждый пункт вылезал за ширину рельса,
    // формируя горизонтальное переполнение прокручиваемой панели.
    .gm-label {
        display: none;
        flex: 0 1 auto;
        min-width: 0;
        margin-left: 12px;                // постоянный отступ (не анимируется)
        white-space: nowrap;
        overflow: hidden;                 // фолбэк для движков без clip
        overflow: clip;                   // clip, а не hidden — без семантики прокрутки
        text-overflow: ellipsis;
        // font-size: 14px;
        line-height: 1.4;
        opacity: 0;
        transition: opacity var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-shell.is-expanded .gm-label {
        display: block;
        opacity: 1;
    }

    /* ── Состояния пунктов ─────────────────────────────────────────────── */
    .gm-link:hover {
        background-color: var(--gm-item-hover-bg);
    }

    .gm-item.is-active .gm-link,
    .gm-item.is-active .gm-link:hover {
        background-color: var(--gm-item-active-bg);
        color: var(--gm-item-active-color);
    }

    .gm-item.is-disabled .gm-link {
        opacity: .4;
        pointer-events: none;
        cursor: default;
    }

    /* ── Footer (пользователь + настройки) ─────────────────────────────── */
    // Прижат к низу панели. В рельсе — колонка (аватар над шестерёнкой, обе
    // иконки в колонке рельса); в развёрнутом — ряд (пользователь | шестерёнка).
    .gm-footer {
        flex-shrink: 0;
        margin-top: auto;
        padding: 8px var(--gm-panel-pad) 12px;
        border-top: 1px solid var(--gm-item-hover-bg);
        display: flex;
        flex-direction: column;
        align-items: stretch;
        @include flex-gap(2px, column);
    }

    // Развёрнутая панель кладёт подвал в ряд, поэтому фолбэк колонки здесь снимается.
    .gm-shell.is-expanded .gm-footer {
        flex-direction: row;
        align-items: center;
        @include flex-gap(4px);

        html.no-flex-gap & > * + * {
            margin-top: 0;
        }
    }

    // Пользователь — pill как пункты навигации (наследует .gm-link).
    .gm-footer-user {
        flex: 1 1 auto;
        min-width: 0;
    }

    // Рельс: паддинг пункта (10px) и паддинг футера вместе оставляют для аватара
    // меньше места, чем его размер. Растягиваем pill на всю ширину рельса
    // (компенсируя паддинг футера отрицательным margin) и центрируем аватар
    // в этой полной ширине — как у иконок пунктов. Подпись в рельсе из потока
    // уже убрана общим правилом, поэтому центрируется именно аватар.
    .gm-shell:not(.is-expanded) .gm-footer-user {
        padding-left: 0;
        padding-right: 0;
        margin-left: calc(-1 * var(--gm-panel-pad));
        margin-right: calc(-1 * var(--gm-panel-pad));
        width: var(--gm-rail-width);
        justify-content: center;
    }

    .gm-footer-avatar {
        flex: 0 0 auto;
    }

    // Дропдаун-обёртка настроек: в рельсе занимает всю ширину (шестерёнка по
    // центру колонки), в развёрнутом — компактный квадрат справа.
    .gm-footer-settings {
        flex: 0 0 auto;
        width: 100%;
    }

    .gm-shell.is-expanded .gm-footer-settings {
        width: auto;
    }

    .gm-footer-settings :deep(.gm-footer-settings-button) {
        width: 100%;
    }

    .gm-footer-settings-trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: var(--gm-item-height);
        border-radius: var(--gm-item-radius);
        color: var(--gm-item-color);
        cursor: pointer;
        transition: background-color var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-footer-settings-trigger:hover {
        background-color: var(--gm-item-hover-bg);
    }

    .gm-shell.is-expanded .gm-footer-settings-trigger {
        width: var(--gm-item-height);
    }

    // Пункты выпадающего меню настроек. Dropdown телепортирует область в <body>,
    // но слот рендерится этим компонентом → scoped-атрибут сохраняется, стиль
    // применяется и после телепорта.
    .gm-footer-menu-item {
        display: block;
        padding: 8px 12px;
        min-width: 180px;
        font-size: 14px;
        line-height: 1.4;
        color: var(--gm-item-color);
        text-decoration: none;
        border-radius: var(--gm-item-radius);
        white-space: nowrap;
        cursor: pointer;
        transition: background-color var(--gm-ease-dur) var(--gm-ease);
    }

    .gm-footer-menu-item:hover {
        background-color: var(--gm-item-hover-bg);
    }

    // Меню отключено (нет аккаунта и т.п.) — футер тоже неинтерактивен.
    .gm-shell.is-disabled .gm-footer {
        opacity: .4;
        pointer-events: none;
    }

</style>
