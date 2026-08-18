import { defineRule, configure } from 'vee-validate';

import { i18n } from './i18n.config.js';

// Сообщения правил хранятся как английские ключи с подстановками; когда перевода
// нет, вернётся сам ключ — подставить значения в него всё равно нужно.
const $t = (key, params = {}) => Object.entries(params).reduce(
    (message, [name, value]) => message.split(`{${name}}`).join(value),
    String(i18n.global.t(key)),
);

const isEmpty = (value) => value === null
    || value === undefined
    || value === ''
    || (Array.isArray(value) && value.length === 0);

/**
 * Набор правил проверки форм кабинета. Формы зовут проверку по имени правила,
 * поэтому набор обязан быть зарегистрирован до первой отправки формы —
 * иначе проверка падает на неизвестном имени.
 */
export default (() => {

    // Проверка запускается только по явному вызову из формы: подсветка полей
    // на каждое нажатие клавиши мешает заполнять форму.
    configure({
        validateOnBlur: false,
        validateOnChange: false,
        validateOnInput: false,
        validateOnModelUpdate: false,
    });

    defineRule('required', value => isEmpty(value)
        ? '* ' + $t('this field is required')
        : true);

    defineRule('notempty', value => isEmpty(value)
        ? '* ' + $t('this field is required')
        : true);

    defineRule('notZero', value => value == 0
        ? '* ' + $t('this field is required')
        : true);

    defineRule('number', value => {
        if (isEmpty(value))
            return true;

        return isNaN(Number(value))
            ? '* ' + $t('this field must be a valid number')
            : true;
    });

    defineRule('string', value => {
        if (isEmpty(value))
            return true;

        return typeof value !== 'string'
            ? '* ' + $t('this field must be a text')
            : true;
    });

    defineRule('email', value => {
        if (isEmpty(value))
            return true;

        const emailRE = /^(?!\.)(?!.*\.\.)([A-Z0-9_'+\-\.]*)[A-Z0-9_+-]@([A-Z0-9][A-Z0-9\-]*\.)+[A-Z]{2,}$/i;

        return emailRE.test(value)
            ? true
            : '* ' + $t('this field must be a valid email');
    });

    defineRule('phone', value => {
        if (isEmpty(value))
            return true;

        // Разделители набора значения не несут, поэтому снимаются перед проверкой.
        const cleaned = String(value).replace(/[\s()-]/g, '');

        return /^\+?[0-9]{7,15}$/.test(cleaned)
            ? true
            : '* ' + $t('this field must be a valid phone number');
    });

    defineRule('password', value => {
        if (isEmpty(value))
            return true;

        const length = 8;

        return String(value).length < length
            ? '* ' + $t('must be at least {length} characters', { length })
            : true;
    });

    defineRule('confirmed', (value, [target], ctx) => value === ctx.form[target]
        ? true
        : '* ' + $t('Password not confirmed'));

    defineRule('min', (value, [length]) => {
        if (isEmpty(value))
            return true;

        return String(value).length < length
            ? '* ' + $t('must be at least {length} characters', { length })
            : true;
    });

    defineRule('max', (value, [length]) => {
        if (isEmpty(value))
            return true;

        return String(value).length > length
            ? '* ' + $t('must be at most {length} characters', { length })
            : true;
    });

    defineRule('minwords', (value, [length]) => {
        if (isEmpty(value))
            return true;

        const words = String(value).trim().split(/\s+/).filter(Boolean).length;

        return words < length
            ? '* ' + $t('must be at least {length} words', { length })
            : true;
    });

    defineRule('notcontains', (value, words) => {
        if (isEmpty(value) || !words.length)
            return true;

        const found = words.some(word => String(value).toLowerCase().includes(word));

        return found
            ? '* ' + $t('don\'t use words like {words}', { words })
            : true;
    });

})();
