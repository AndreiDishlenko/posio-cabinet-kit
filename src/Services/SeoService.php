<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\Models\SeoMeta;
use Posio\CabinetKit\Support\JsonLdObject;

class SeoService {

    protected Collection $all_seo_data;

    protected array $items;
    protected array $seo_meta = [];
    protected array $seo_data = [];

	protected array $overrides = [];
	protected array $extra_jsonld = [];

    public function __construct() {
    }

	// Мета страницы, которую не завести записью раздела SEO: у всех товаров один
	// маршрут, а заголовок, описание и картинка у каждого свои. Ключи — как у
	// записи раздела (meta_title, meta_description, og_image, index…) и только
	// для страниц без записи: og_type, og_image_width/height, follow (ссылки
	// закрытой страницы открыты). Действует на текущий запрос и выигрывает у записи.
	public function override(array $meta): static {
		$this->overrides = array_replace($this->overrides, $meta);

		return $this;
	}

	// Узел микроразметки страницы сверх общего графа (товар, статья).
	public function addJsonLd(array $node): static {
		$this->extra_jsonld[] = $node;

		return $this;
	}

    public function getInfo() : array {
		// var_dump('<br>SeoService.getSeoData');
		$cp_seo_data = $this->currentSeoData();

		// OG image: per-page override or global fallback from config
		$default_og_image = config('general.default_og_image', '');
		$og_image = !empty($cp_seo_data['og_image']) ? $cp_seo_data['og_image'] : $default_og_image;
		[$og_image_width, $og_image_height] = $this->ogImageSize($og_image, $cp_seo_data, $default_og_image);
		$og_image = $this->toAbsoluteUrl($og_image);

		$enable_index = !empty($cp_seo_data['index']);

		$result = [
            ...Collect($this->seo_data)->except(['id', 'locale', 'route_name', 'created_at', 'updated_at']),
            'enableindex'   => $enable_index,
			// Закрытая от индекса страница может отдавать ссылочный вес дальше
			// (сравнение, фильтры) — это решает переопределение контроллера.
			'robots'		=> $enable_index ? 'index, follow' : 'noindex, ' . (!empty($cp_seo_data['follow']) ? 'follow' : 'nofollow'),
			'site_name'		=> app(SiteSettingsService::class)->siteName(),
			'canonical'		=> $this->onSiteHost(url()->current()),
			'meta_data'		=> $this->currentPageMetaData(),
			'alternate'		=> $this->alternatePageData(),
			'breadcrumbs'	=> app(BreadcrumbService::class)->get(),
            'jsonld'        => $this->withExtraJsonLd(app(JsonLdObject::class)->make($cp_seo_data)->get()),
			// Open Graph
			'og_image'        => $og_image,
			'og_image_width'  => $og_image_width,
			'og_image_height' => $og_image_height,
			// Пусто — тип задаёт layout (по умолчанию website).
			'og_type'         => $cp_seo_data['og_type'] ?? '',
			'og_title'       => $cp_seo_data['og_title'] ?? '',
			'og_description' => $cp_seo_data['og_description'] ?? '',
			// Twitter Card
			'twitter_image'       => $this->toAbsoluteUrl(!empty($cp_seo_data['twitter_image']) ? $cp_seo_data['twitter_image'] : $og_image),
			'twitter_title'       => $cp_seo_data['twitter_title'] ?? '',
			'twitter_description' => $cp_seo_data['twitter_description'] ?? '',
			// Locale for OG
			'og_locale'           => $this->ogLocale(app()->getLocale()),
			'og_locale_alternate' => $this->ogLocaleAlternates(app()->getLocale()),
        ];

		// dd($result);

        return $result;
    }

	/**
	 * Strip the locale suffix from the current route name.
	 * Routes are now registered as `{base}.{locale}` (e.g. usecases.coffeeshop.uk).
	 * SeoMeta records store only the base name (e.g. usecases.coffeeshop).
	 */
	public function baseRouteName(): string {
		$name = Route::currentRouteName() ?? '';

		return preg_replace('/\.(' . $this->localePattern() . ')$/', '', $name);
	}

	public function currentPageMetaData() {
		$cp_seo_data = $this->currentSeoData();

		$result = [
			'page_name' => $cp_seo_data['page_name'] ?? '',
			'title' => $cp_seo_data['meta_title'] ?? '',
			'description' => $cp_seo_data['meta_description'] ?? '',
			'keywords' => $cp_seo_data['meta_keywords'] ?? '',
		];

		return $result;
	}

	public function alternatePageData() {
		$base_route_name = $this->baseRouteName();

		$result = [
    		'x-default' => $this->onSiteHost(str_replace('/' . app()->getLocale(), '', url()->current())),
		];

		// Передаём параметры текущего маршрута (например token у password.reset),
		// иначе loc_route бросает UrlGenerationException на параметризованных роутах.
		$route_params = Route::current() ? Route::current()->parameters() : [];

		if ( !empty($base_route_name) )
			collect( config('general.locales') )->each(function($locale) use (&$result, $base_route_name, $route_params) {
				$result[$locale] = $this->onSiteHost(loc_route($base_route_name, $locale, $route_params));
			});

		return $result;
	}

	public function getAllSeoData() : Collection {
		if ( isset($this->all_seo_data) && count($this->all_seo_data) )
			return $this->all_seo_data;

		$this->all_seo_data = SeoMeta::all();

		return $this->all_seo_data;
	}

	public function getRouteSeoData($route_name, $locale='') {
		$all_seo_data = $this->getAllSeoData();

        $locale = !empty($locale) ? $locale : app()->getLocale();

        $route_seo_data = $all_seo_data->first(function ($item) use ($route_name, $locale) {
            return $item['route_name'] === $route_name &&
                  ($item['locale'] === $locale || $item['locale'] === '');
        });

        return $route_seo_data;
	}

	// Запись текущей страницы с переопределениями контроллера поверх.
	protected function currentSeoData(): array {
		$record = $this->getRouteSeoData($this->baseRouteName());

		return array_replace($record ? $record->toArray() : [], $this->overrides);
	}

	protected function withExtraJsonLd(array $jsonld): array {
		if ( empty($this->extra_jsonld) )
			return $jsonld;

		$jsonld['@graph'] = [...($jsonld['@graph'] ?? []), ...$this->extra_jsonld];

		return $jsonld;
	}

	// Канонический адрес всегда на основном домене сайта и без параметров запроса:
	// страница, открытая через алиас хоста (www) или с метками и мусором в адресе,
	// иначе объявит канонической саму себя, и поисковик заведёт на неё дубль.
	protected function onSiteHost(string $url): string {
		$site_root = rtrim((string) config('app.url'), '/');

		if ( empty($site_root) )
			return strtok($url, '?');

		return $site_root . (parse_url($url, PHP_URL_PATH) ?? '');
	}

	// Размер в теге должен совпадать с файлом: соцсети по нему строят превью ещё
	// до загрузки картинки. Явный размер из переопределения, затем замер файла
	// сайта; конфиговый размер верен только для картинки по умолчанию. Размер
	// неизвестен — теги размера не выводятся вовсе, лучше чем неверные.
	protected function ogImageSize(string $image, array $seo_data, string $default_image): array {
		if ( empty($image) )
			return [null, null];

		if ( !empty($seo_data['og_image_width']) && !empty($seo_data['og_image_height']) )
			return [(int) $seo_data['og_image_width'], (int) $seo_data['og_image_height']];

		$size = $this->localImageSize($image);
		if ( $size )
			return $size;

		if ( $image === $default_image )
			return [config('general.default_og_image_width', 1200), config('general.default_og_image_height', 628)];

		return [null, null];
	}

	protected function localImageSize(string $url): ?array {
		$host = parse_url($url, PHP_URL_HOST);
		if ( $host && $host !== parse_url((string) config('app.url'), PHP_URL_HOST) && $host !== request()->getHost() )
			return null;

		$path = public_path(ltrim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/'));
		if ( !is_file($path) )
			return null;

		$size = @getimagesize($path);

		return $size ? [$size[0], $size[1]] : null;
	}

	protected function toAbsoluteUrl(string $path): string {
		if ( empty($path) )
			return '';
		if ( str_starts_with($path, 'http://') || str_starts_with($path, 'https://') )
			return $path;
		return rtrim(url('/'), '/') . '/' . ltrim($path, '/');
	}

	// Суффиксы локалей, которые срезаются с имени маршрута, — те же локали, что
	// объявлены у публичной части. Список нужен выражением, поэтому собирается
	// из конфига, а не зашит в шаблон.
	protected function localePattern(): string {
		$locales = array_filter((array) config('general.locales', []), fn ($locale) => is_string($locale) && $locale !== '');

		if ( empty($locales) )
			return 'en';

		return implode('|', array_map(fn ($locale) => preg_quote($locale, '/'), $locales));
	}

	protected function ogLocale(string $locale): string {
		return match($locale) {
			'uk'    => 'uk_UA',
			'ru'    => 'ru_RU',
			default => 'en_US',
		};
	}

	// Альтернативы — только реально объявленные языки сайта: одноязычный сайт,
	// заявивший чужие локали, вводит соцсети и поисковики в заблуждение.
	protected function ogLocaleAlternates(string $current): array {
		$current_og_locale = $this->ogLocale($current);

		return collect((array) config('general.locales', []))
			->filter(fn ($locale) => is_string($locale) && $locale !== '')
			->map(fn ($locale) => $this->ogLocale($locale))
			->reject(fn ($og_locale) => $og_locale === $current_og_locale)
			->unique()
			->values()
			->all();
	}

}
