<?php

/*
|--------------------------------------------------------------------------
| SEO / Structured data (JSON-LD)
|--------------------------------------------------------------------------
|
| Настройки микроразметки Schema.org, которую собирает JsonLdObject и отдаёт
| SeoService::getInfo() во фронт (resources/js/сomponents/SeoMeta.vue).
|
| Значения ниже — обезличенные заготовки: своё название бренда и логотип
| оператор задаёт в кабинете (раздел «Налаштування сайту»), а этот файл
| описывает то, что редактированию из интерфейса не подлежит — организацию,
| контакты, описание продукта и состав главной навигации.
|
| Дефолты OG-изображения остаются в config/general.php (default_og_image*).
|
*/

return [

	// Короткий бренд: og:site_name, WebSite.alternateName, имя Organization.
	// Название сайта из настроек кабинета перекрывает его везде, кроме
	// alternateName — там намеренно остаётся короткий неизменяемый бренд.
	'brand_name' => env('APP_NAME', 'Cabinet'),
	'org_name'   => env('APP_NAME', 'Cabinet'),

	// Логотип организации (квадратный, для Google). Абсолютный URL строится в
	// JsonLdObject. Пока в настройках нет своего логотипа — обезличенная заготовка.
	'org_logo'   => '/brand-assets/symbol_dark_theme.svg',

	// Контактный email поддержки (Organization.email + contactPoint).
	'org_email'  => env('SEO_ORG_EMAIL'),

	// Профили в соцсетях (Organization.sameAs). Заполнять реальными URL — пустые
	// значения отфильтровываются, плейсхолдеры в схему не попадают.
	'org_sameas' => array_values(array_filter([
		env('SOCIAL_FACEBOOK'),
		env('SOCIAL_INSTAGRAM'),
		env('SOCIAL_YOUTUBE'),
		env('SOCIAL_LINKEDIN'),
		env('SOCIAL_TIKTOK'),
	])),

	// Языки поддержки в Organization.contactPoint.
	'org_languages' => ['Ukrainian', 'English'],

	// Описание организации по локали (Organization.description).
	'org_description' => [
		'en' => '',
		'uk' => '',
	],

	// Карточка продукта (SoftwareApplication) в микроразметке. Нужна сайтам
	// сервиса/приложения; обычному сайту-визитке — нет, тогда `enabled` = false
	// и узел не попадает в граф даже на главной.
	'software' => [
		'enabled'                => false,
		'application_category'   => 'BusinessApplication',
		'application_subcategory' => '',
		'operating_system'       => 'Web',
		'languages'              => ['uk', 'en'],
		'offer' => [
			'price'         => '0',
			'currency'      => 'UAH',
			'availability'  => 'https://schema.org/InStock',
		],
	],

	// Описание продукта (SoftwareApplication.description) по локали.
	'software_description' => [
		'en' => '',
		'uk' => '',
	],

	// Ключевые возможности (SoftwareApplication.featureList) по локали.
	'software_features' => [
		'en' => [],
		'uk' => [],
	],

	// Схема основной навигации (ItemList из SiteNavigationElement) — подсказка
	// Google для sitelinks. Перечислять базовые имена маршрутов публичной части
	// БЕЗ суффикса локали; имя пункта берётся из page_name соответствующей
	// SEO-записи. Маршрут, которого нет, молча пропускается.
	'sitenav_routes' => [
		'home',
	],

];
