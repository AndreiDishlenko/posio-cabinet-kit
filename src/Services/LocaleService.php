<?php

namespace Posio\CabinetKit\Services;

use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


class LocaleService {

	// Языки аудитории, которой по языку браузера отдаём украинскую версию; русскую
	// посетитель получает только выбрав её сам. Все остальные языки — на английскую.
	public $slavicLocales = ['uk', 'ru', 'be'];

    public function __construct(
    ) {}

	static public function localeFromLanguage(string $source_language) : string {
		$locale = $source_language;

		if ( in_array($source_language, (new self())->slavicLocales) )
			$locale = 'uk';

		return $locale;
	}

	static public function checkLocale(string $locale) : bool {
		return !empty($locale) && strlen($locale) === 2 && in_array($locale, config('general.locales'));
	}

	// Части сервиса без русского перевода (кабинет, касса, PHP-строки) обслуживаются
	// украинской локалью: русская никогда не должна опускаться до английской.
	static public function localeWithoutRussian(string $locale) : string {
		return $locale === 'ru' ? 'uk' : $locale;
	}

	// Локаль приложения для PHP-переводов: русских строк нет, поэтому недостающие
	// берём из украинской. Остальным языкам — запасной язык сайта, а не язык по
	// умолчанию: иначе английская страница добирала бы недостающее по-украински.
	static public function applyAppLocale(?string $locale) : void {
		if ( !self::checkLocale( (string) $locale ) )
			return;

		App::setLocale( $locale );
		App::setFallbackLocale( $locale === 'ru' ? 'uk' : (config('general.site_fallback_locale') ?: config('general.default_locale')) );
	}

	static public function detectLocale(Request $request, ?string $fallback_locale = null) : string {
		$locale = '';

		// Get fom cookie
		if ( $request->hasCookie('locale') )
			$locale = $request->cookie('locale');

		// Get from browser
		if ( !self::checkLocale( (string) $locale ) )
			$locale = (string) self::browserLocale( (string) $request->server('HTTP_ACCEPT_LANGUAGE') );

		// Нераспознанный язык — скорее иностранец, чем украинец: и на сайте, и на входе,
		// и при регистрации он должен получить один и тот же язык.
		if ( !self::checkLocale($locale) )
			$locale = $fallback_locale ?: config('general.site_fallback_locale');

		return $locale;
	}

	// Первый поддерживаемый язык из списка браузера в порядке предпочтения: украинец
	// с польским браузером обычно держит украинский вторым, и его нельзя терять.
	static public function browserLocale(string $accept_language) : ?string {
		$languages = [];

		foreach ( explode(',', $accept_language) as $position => $item ) {
			$parts = explode(';', trim($item));
			$language = strtolower( explode('-', trim($parts[0]))[0] );
			$weight = 1.0;

			foreach ( array_slice($parts, 1) as $param ) {
				$param = trim($param);
				if ( str_starts_with($param, 'q=') )
					$weight = (float) substr($param, 2);
			}

			// Нулевой вес — явный отказ от языка.
			if ( $language === '' || $language === '*' || $weight <= 0 )
				continue;

			$languages[] = [$language, $weight, $position];
		}

		// При равном весе решает порядок в заголовке.
		usort($languages, fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

		foreach ( $languages as [$language] ) {
			$locale = self::localeFromLanguage($language);
			if ( self::checkLocale($locale) )
				return $locale;
		}

		return null;
	}

	static public function firstSegmentLocale(Request $request) {
		$firstSegment = $request->segment(1);

		if ( $firstSegment && in_array( $firstSegment, config('general.locales') ) )
			return $firstSegment;

		return null;
	}

	static public function getLocale(Request $request, ?string $fallback_locale = null) : string {
		$route_locale_prefix = self::firstSegmentLocale($request);

		if ( $route_locale_prefix )
			return $route_locale_prefix;

		// Сайт, у которого основной язык живёт без префикса: адрес без префикса и
		// есть выбор этого языка, угадывать по куке и браузеру тут нечего.
		$unprefixed_locale = (string) config('general.unprefixed_locale');

		if ( self::checkLocale($unprefixed_locale) )
			return $unprefixed_locale;

		if ( !Auth::check() )
			$locale = self::detectLocale($request, $fallback_locale);
		else {
			$user_locale = (string) self::userLocale(Auth::user());

			if ( self::checkLocale($user_locale) )
				$locale = $user_locale;
			else {
				$locale = self::detectLocale($request, $fallback_locale);
			}
		}

		// Ни один из источников не дал пригодного значения — берём язык по умолчанию,
		// иначе закрепление локали ниже по цепочке упало бы с ошибкой.
		return self::checkLocale( (string) $locale ) ? $locale : config('general.default_locale');
	}

	static public function renewLocale(Request $request, ?string $fallback_locale = null) {

		$locale = self::getLocale($request, $fallback_locale);

		self::setLocale($locale);

		return $locale;
	}

	// Локаль текущего запроса: сессия, PHP-переводы и кука на будущие визиты.
	// Профиль пользователя намеренно не трогаем: адрес открытой страницы — не выбор
	// языка, иначе один переход по чужой ссылке навсегда переключал бы кабинет.
	static public function setLocale(string $locale) {
		if ( !in_array($locale, config('general.locales') ) )
            throw new Error('Unknown locale');

		Session::put('locale', $locale);
		self::applyAppLocale( $locale );
		cookie()->queue('locale', $locale, 60 * 24 * 30);

		return $locale;
	}

	// Осознанное переключение языка пользователем — единственное, что закрепляется
	// в профиле и потому переживает смену устройства и очистку куки.
	static public function rememberLocale(string $locale) {
		self::setLocale($locale);

		if ( Auth::user() && method_exists(Auth::user(), 'setSetting') )
			Auth::user()->setSetting('locale', $locale);

		return $locale;
	}

	// Язык из профиля: у пользователя хоста метода языка может не быть, настройки — есть.
	static public function userLocale($user) : ?string {
		if ( $user && method_exists($user, 'getSetting') )
			return $user->getSetting('locale');

		return null;
	}

}
