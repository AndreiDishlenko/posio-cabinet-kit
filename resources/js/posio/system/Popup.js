// Сборка без стилей: в стандартной они вшиты в JS и не проходят через сборщик,
// из-за чего на нижней планке Apple диалог остаётся почти без оформления.
// Оформление подключается общим стилем проекта (копия в scss/vendor).
import Swal from 'sweetalert2/dist/sweetalert2.esm.js'
import { $t } from '@/js/i18n.config'

// Базовый вид диалога — его видит касса: классы карточки задают фон, отступы и
// типографику, иконка-акцент не показывается.
const base_appearance = {
    classes: {
        // container           : 'popup card max-w-[90%]',
        popup               : 'card popup',
        header              : 'popup-header',
        title               : 'card-header popup-title !mt-0',
        // icon                : '...',
        // image               : '...',
        htmlContainer       : 'card-body popup-body',
        input               : 'form-control form-control-lg popup-input',
        inputLabel          : 'form-label',
        validationMessage   : 'form-error',
        actions             : 'popup-footer',
        confirmButton       : 'button primary-button popup-button px-8',
        denyButton          : 'button popup-button px-8',
        cancelButton        : 'button popup-button px-8 ms-4',
        closeButton         : 'button popup-button px-8',
        footer              : '',
        // loader          : '...',
        // timerProgressBar: '....',
    },
    // Без текста заголовок в этом виде отрывается от кнопок лишним отступом.
    tighten_empty_body    : true,
    danger_confirm_button : '',
    icons                 : null,
}

// Акцентный вид кабинета: цветная иконка слева от текста, заголовок и описание по
// левому краю, опасное подтверждение — красной кнопкой.
const accented_appearance = {
    classes: {
        popup               : 'dialog',
        title               : 'dialog-title',
        htmlContainer       : 'dialog-body',
        icon                : 'dialog-icon',
        input               : 'form-control dialog-input',
        inputLabel          : 'form-label dialog-input-label',
        validationMessage   : 'form-error dialog-error',
        actions             : 'dialog-actions',
        confirmButton       : 'button button-md primary-button',
        denyButton          : 'button button-md',
        cancelButton        : 'button button-md outline-button',
        closeButton         : 'dialog-close',
        footer              : 'dialog-footer',
    },
    tighten_empty_body    : false,
    danger_confirm_button : 'button button-md danger-button',
    // Тон обращения → иконка библиотеки: вопрос, предупреждение, сообщение.
    icons                 : { ask: 'question', danger: 'warning', info: 'info' },
}

class PopupClass {
    instances
    rules = {
        password: (value) => {
            if (!value) 
                Swal.showValidationMessage(  $t('Password can not be empty') );
            
            return value;
        },
        pin: (value) => {
            if (!value) 
                Swal.showValidationMessage(  $t('Pin can not be empty') );

            if (value.length!=4) 
                Swal.showValidationMessage(  $t('Pin should be 4 digits length') );


            return value;
        }
    }
    defaultOptions = {
        buttonsStyling:     false,
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off', 
            autocomplete: 'new-password', 
            value: '', 
        },
        showCancelButton:   true,
        confirmButtonText:  $t('Confirm'),
        cancelButtonText:   $t('Cancel'),
        loaderHtml: '',
        showLoaderOnConfirm: false
    }

    // Ключи, которыми распоряжается сам сервис диалогов: до библиотеки они не
    // доходят — на незнакомый параметр она ругается в консоль.
    own_option_keys = ['title', 'yes_text', 'no_text', 'validation', 'placeholder', 'danger']

    constructor() {
        this.instances = [];
        this.appearance = base_appearance;
    }

    // Вызывается точкой входа той части сервиса, где диалоги оформлены акцентно.
    useAccentedDialogs() {
        this.appearance = accented_appearance;
    }

    // Параметры для библиотеки: оформление текущего вида, поверх него — то, что
    // передал вызов, за вычетом собственных ключей сервиса.
    buildParams(tone, text, options, params) {
        const passed = { ...options };
        this.own_option_keys.forEach(key => delete passed[key]);

        const classes = { ...this.appearance.classes };

        if ( !text && this.appearance.tighten_empty_body )
            classes.htmlContainer += ' !mt-0';

        if ( options?.danger && this.appearance.danger_confirm_button )
            classes.confirmButton = this.appearance.danger_confirm_button;

        const icon = this.appearance.icons?.[ options?.danger ? 'danger' : tone ];

        return {
            ...this.defaultOptions,
            html:           text,
            buttonsStyling: false,
            ...(icon ? { icon } : {}),
            ...params,
            customClass:    classes,
            ...passed,
        };
    }

    // Единая точка обращения к библиотеке: разбор её результата задаёт вызывающий.
    fire(params, readResult) {
        return new Promise((resolve) => {
            Swal.fire(params).then((result) => resolve(readResult(result)));
        });
    }

    message(text, options) {
        let params = this.buildParams('info', $t(text), options, {
            title: options?.title ? $t(options.title) : $t('Message'),
            showCancelButton: false,
            confirmButtonText: $t('Ok'),
            // cancelButtonText: 'Нет',
        });

        return this.fire(params, (result) => result.isConfirmed ? 1 : 0);
    }

    confirm_yn(text, options) {
        let params = this.buildParams('ask', text, options, {
            title               : options?.title ? $t(options.title) : $t('Confirmation'),
            showCancelButton    : true,
            confirmButtonText   : options?.yes_text ? options?.yes_text : $t('Yes'),
            cancelButtonText    : options?.no_text ? options?.no_text : $t('No'),
        });

        return this.fire(params, (result) => result.isConfirmed ? 1 : 0);
    }

    // yes / no / cancel — в отличие от confirm_yn различает кнопку "No" и отмену
    // запроса (Esc, клик по фону, крестик). Возвращает: 1 — Yes, 0 — No, -1 — отмена.
    confirm_ync(text, options) {
        let params = this.buildParams('ask', text, options, {
            title               : options?.title ? $t(options.title) : $t('Confirmation'),
            showCancelButton    : true,
            confirmButtonText   : options?.yes_text ? options?.yes_text : $t('Yes'),
            cancelButtonText    : options?.no_text ? options?.no_text : $t('No'),
        });

        return this.fire(params, (result) => {
            if (result.isConfirmed)
                return 1;

            if (result.dismiss === Swal.DismissReason.cancel)
                return 0;

            // Esc / клик по фону / крестик — полная отмена действия
            return -1;
        });
    }

    password_confirm(text, options) {
        let params = this.buildParams('ask', text, options, {
            title:            options?.title ? $t(options.title) : $t('Confirmation'),
            input:            'password',
            inputPlaceholder: options?.placeholder ? $t(options.placeholder) : $t('Enter password to confirm'),
            preConfirm:       options?.validation ? this.rules[options?.validation] : ()=>{},
        });

        return this.fire(params, (result) => result.isConfirmed ? result.value : null);
    }

    notification() {
        console.warn('[Popup.notification] is not defined');
    }

    // Диалог поверх формы не видит popstate (нативный слушатель Escape гасит
    // его сам) — кнопка/жест «назад» проверяют это, чтобы не открыть второй
    // диалог поверх уже показанного.
    isOpen() {
        return Swal.isVisible();
    }
    
    // confirm(settings) {
    //     const dialog = $.confirm(settings)
    //     this.instances.push(dialog)
    // }

    // form(form, yes_action='', cancel_action='') {
    //     let set = {
    //         title: '',
    //         content: form,
    //         escapeKey: true,
    //         scrollToPreviousElement: false, 			// If true focus will return to hidden button and then button.focus() will not work
    //         backgroundDismiss: false,
    //         alignMiddle: true,
    //         buttons: {
    //             formSubmit: {
    //                 text: msg('Yes'),
    //                 btnClass: 'btn-blue',
    //                 keys: ['enter', 'space'],
    //                 action: yes_action,
    //             }
    //         },
    //         onContentReady: function () {
    //             // bind to events
    //             var jc = this;
    //             this.$content.find('.tabledit-input').select();
    //             this.$content.find('form').on('submit', function (e) {
    //                 // if the user submits the form by pressing enter in the field.
    //                 e.preventDefault();
    //                 jc.$$formSubmit.trigger('click'); // reference the button and click it
    //             });
    //         }
    //     };
    
    //     if (cancel_action) 
    //         set.buttons.cancel = {
    //             text: msg('Cancel'),
    //             keys: ['esc'],
    //             action: cancel_action
    //         };
    
    //     this.confirm(set);
    // }

    // input(text, type, params) {
    //     if (!type)
    //         type='text';
    //     if (!params)
    //         params={};
    
    //     let maxlength = params['maxlength'] ? 'maxlength="'+params['maxlength']+'"' : '';
    
    //     return new Promise((resolve, reject) => {
    //         // var result = '';
    //         this.form(
    //             '<div class="form-group">\
    //                 '+text+'\
    //                 <input type="' + type + '" class="tabledit-input name form-control mt-3" required ' + maxlength + '/>\
    //             </div>',
    //             function () {
    //                 resolve(this.$content.find('input').val());
    //             },
    //             function () {
    //                 resolve(false);
    //             }
    //         );
    //         // return result;
    //     });
    // }

    // closeAll() {
    //     this.instances.forEach(instance => {
    //         if (instance.isOpen()) {
    //             instance.close();
    //         }
    //     });
    // }
}

const Popup = new PopupClass()

export { Popup }









// $.popup_form = async function( form, yes_action='', cancel_action='' ) {
//     let set = {
//         title: '',
//         content: form,
//         escapeKey: true,
//         scrollToPreviousElement: false, 			// If true focus will return to hidden button and then button.focus() will not work
//         backgroundDismiss: false,
//         alignMiddle: true,
//         buttons: {
//             formSubmit: {
//                 text: msg('Yes'),
//                 btnClass: 'btn-blue',
//                 keys: ['enter', 'space'],
//                 action: yes_action,
//             }
//         },
//         onContentReady: function () {
//             // bind to events
//             var jc = this;
//             this.$content.find('.tabledit-input').select();
//             this.$content.find('form').on('submit', function (e) {
//                 // if the user submits the form by pressing enter in the field.
//                 e.preventDefault();
//                 jc.$$formSubmit.trigger('click'); // reference the button and click it
//             });
//         }
//     };

//     if (cancel_action) 
//         set.buttons.cancel = {
//             text: msg('Cancel'),
//             keys: ['esc'],
//             action: cancel_action
//         };

//     $.confirm(set);
// };

// $.confirmation = function(text, yes_action, no_action, cancel_action) {
//     let set = {
//         title: 'Подтверждение',
//         content: text,
//         escapeKey: true,
//         scrollToPreviousElement: false, 			// If true focus will return to hidden button and then button.focus() will not work
//         backgroundDismiss: true,
//         alignMiddle: true,
//         buttons: {
//             confirm: {
//                 text: msg('Yes'),
//                 btnClass: 'btn-blue',
//                 keys: ['enter', 'space'],
//                 action: yes_action,
//             },
//         }
//     };

//     if (no_action) {
//         $.extend(set['buttons'], {
//             no: {
//                 text: msg('No'),
//                 action: no_action,
//             }
//         });
//     };

//     $.extend(set['buttons'], {
//         cancel: {
//             text: msg('Cancel'),
//             keys: ['esc'],
//             action: cancel_action,
//         }
//     });

//     $.confirm(set);
// };

// $.popup_notification = async function(text) {
//     return new Promise((resolve, reject) => {
//         return $.popup_form('<center>'+msg(text)+'</center>', ()=>resolve(1));
//     });
// }

// Promise confirm yes/no
// $.popup_confirm_yn = async function(text) {
//     return new Promise((resolve, reject) => {
//         return $.popup_form(
//             '<center>'+msg(text)+'</center>', 
//             ()=>resolve(1), 
//             ()=>resolve(0)
//         );
//     });
// };

// $.popup_confirm_yn('aaa');

// Promise yes/no/cancel
// $.popup_confirm_ync = async function(text, yes_action, no_action, cancel_action) {
//     return new Promise((resolve, reject) => {
//         return $.confirmation(msg(text),
//             ()=>{resolve(1)},
//             ()=>{resolve(-1)},
//             ()=>{resolve(0)}
//         )
//     });
// }

// $.popup_input = async function (text, type, params) {
//     if (!type)
//         type='text';
//     if (!params)
//         params={};

//     let maxlength = params['maxlength'] ? 'maxlength="'+params['maxlength']+'"' : '';

//     return new Promise((resolve, reject) => {
//         // var result = '';
//         $.popup_form(
//             '<div class="form-group">\
//                 '+text+'\
//                 <input type="' + type + '" class="tabledit-input name form-control mt-3" required ' + maxlength + '/>\
//             </div>',
//             function () {
//                 resolve(this.$content.find('input').val());
//             },
//             function () {
//                 resolve(false);
//             }
//         );
//         // return result;
//     });
// }