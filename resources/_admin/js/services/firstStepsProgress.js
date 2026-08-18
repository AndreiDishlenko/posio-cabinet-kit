import { reactive } from 'vue';

// Прогресс первых шагов, общий для всех экземпляров панели: панель пересоздаётся
// при перерисовке страницы кабинета, а ответ на её запрос нередко приходит уже
// после этого — внутри экземпляра знание о закрытом шаге умирало бы вместе с ним,
// и поставленная галочка гасла бы обратно. Пройденный шаг назад не открывается,
// поэтому помнятся только закрытые; при смене аккаунта память обнуляется.
const progress = reactive({ account_id: null, steps: {} });

export function rememberReachedSteps(account_id, steps) {
	if ( progress.account_id !== account_id ) {
		progress.account_id = account_id;
		progress.steps      = {};
	}

	for ( const key in steps ?? {} )
		if ( steps[key] )
			progress.steps[key] = true;
}

// Состояние с бэкенда могло быть посчитано до последнего сохранения — поверх него
// всегда ложатся шаги, о закрытии которых уже известно.
export function withReachedSteps(account_id, steps) {
	const result = { ...steps };

	if ( progress.account_id === account_id )
		for ( const key in progress.steps )
			if ( progress.steps[key] )
				result[key] = true;

	return result;
}
