<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\Models\SeoMeta;
use Posio\CabinetKit\Services\BreadcrumbService;
use Posio\CabinetKit\Services\SeoService;
use Posio\CabinetKit\Services\SiteSettingsService;

class JsonLdObject {

	protected array $data = [];
	protected array $seo_data = [];

	public static function make(array $seo_data = []): static {
        $instance = new static();
        $instance->seo_data = $seo_data;
        $instance->data = [
            '@context'  => 'https://schema.org',
            '@graph'    => []
        ];

		$instance->addWebSite();
		// Организация — глобальная идентичность сайта, присутствует на каждой странице
		// (издатель WebSite/WebPage, источник для брендовых панелей Google).
		$instance->addOrganization();
		$instance->addCurrentPage();

		// Карточка продукта (SoftwareApplication) — на главной по умолчанию,
		// плюс на любой странице, где включён флаг в SEO-редакторе. Сайту без
		// продукта узел не нужен вовсе — тогда он выключен в конфиге.
		$base_route = app(SeoService::class)->baseRouteName();
		if ( config('seo.software.enabled', false) && ($base_route === 'home' || !empty($seo_data['jsonld_add_software'])) )
			$instance->addSoftwareApplication();

		// Навигационная схема (подсказка Google для sitelinks).
		$instance->addSiteNavigation();

		$instance->addBreadcrumbs(Route::currentRouteName(), Route::current()?->parameters() ?? []);

        return $instance;
    }

	// Имя бренда: настройка кабинета, иначе конфигурационный дефолт.
	protected function siteName(): string {
		return app(SiteSettingsService::class)->siteName();
	}

	public function get() {
		return $this->data;
	}

	protected function addWebSite() {
        array_push(
            $this->data['@graph'],
            [
                "@type"         => "WebSite",
                "@id"           => url('/#website'),
                "url"           => rtrim(url('/'), '/'),
                "name"          => $this->siteName(),
                "alternateName" => config('seo.brand_name', config('app.name', 'Cabinet')),
                "description"   => $this->localized('seo.org_description'),
                "inLanguage"    => app()->getLocale(),
                "publisher"     => [ "@id" => url('/#organization') ],
            ],
        );

        return $this;
    }

	public function addCurrentPage() {
		$current_route_name   = Route::currentRouteName();
		$current_route_params = Route::current()?->parameters() ?? [];

		// Данные страницы уже корректно найдены в SeoService::getInfo() (по базовому
		// имени маршрута) и переданы в make(). Повторный lookup по имени с суффиксом
		// локали (home.uk) не совпал бы с route_name в БД (home) → name/description
		// не попадали бы в WebPage. Берём готовый seo_data.
		$page_seo_data = $this->seo_data;
		$seo_options = [
			'inLanguage' => app()->getLocale()
		];

		// Название страницы
		if ( !empty($page_seo_data['meta_title']) )
			$seo_options['name'] = $page_seo_data['meta_title'];

		// Описание страницы
		if ( !empty($page_seo_data['meta_description']) )
			$seo_options['description'] = $page_seo_data['meta_description'];

		// Главное изображение страницы
		$og_image = $this->absUrl(!empty($page_seo_data['og_image']) ? $page_seo_data['og_image'] : config('general.default_og_image', ''));
		if ( $og_image )
			$seo_options['primaryImageOfPage'] = $og_image;

		// Keywords Google официально игнорирует и в мета-тегах, и в JSON-LD.

        $this->addPage( $current_route_name, $current_route_params, $seo_options );

        return $this;
    }

	public function getSeoData($route_name) {
		$seo_data = SeoMeta::where('route_name', $route_name)
            ->where(function($query) {
                $query->where('locale', app()->getLocale())
                      ->orWhereNull('locale');
            })->first();

		$result = !empty($seo_data) ? $seo_data->toArray() : [];

		return $result;
	}

	public function addPage($route_name, $route_params=[], $options=[]) {
        // Роуты без имени (например убранные из Ziggy) — берём текущий URL напрямую,
		// потому что route(null, ...) бросает RouteNotFoundException.
		$url = $route_name ? route( $route_name, $route_params ) : url()->current();

		$new_item = [
			"@type" 		=> "WebPage",
			"url"   		=> $url,
			"@id"   		=> rtrim( $url, '/' ).'/#webpage',
			"isPartOf"      => [ "@id"   => url('/#website') ],
			"about"         => [ "@id"   => url('/#organization') ],
			...$options
		];

		$breadcrumbsItems   = app(BreadcrumbService::class)->get();
		if ( count($breadcrumbsItems) )
			$new_item["breadcrumb"] = [ "@id"   => rtrim( $url, '/') . "/#breadcrumbs" ];

        array_push(
            $this->data['@graph'],
            $new_item
        );

        return $this;
    }

	public function addBreadcrumbs($route_name, $route_params=[]): self {
		$breadcrumbsItems   = app(BreadcrumbService::class)->get();
		if ( !count($breadcrumbsItems) )
			return $this;

		$url = $route_name ? route($route_name, $route_params) : url()->current();

		array_push(
			$this->data['@graph'],
            [
				'@type'     => 'BreadcrumbList',
				"@id"       => rtrim($url, '/') . "/#breadcrumbs",
				'itemListElement' => collect($breadcrumbsItems)->map(function ($item, $index) {
					return [
						'@type' 	 => 'ListItem',
						'position' 	 => $index + 1,
						'name' 		 => $item['name'],
						'item' 		 => $item['url']
					];
				})->toArray(),
			]
        );

        return $this;
    }

	protected function addSoftwareApplication(): self {
		$node = [
			'@type'                  => 'SoftwareApplication',
			'@id'                    => url('/#software'),
			'name'                   => $this->siteName(),
			'applicationCategory'    => config('seo.software.application_category', 'BusinessApplication'),
			'operatingSystem'        => config('seo.software.operating_system', 'Web'),
			'url'                    => rtrim(url('/'), '/'),
			'inLanguage'             => (array) config('seo.software.languages', ['en']),
			'publisher'              => [ '@id' => url('/#organization') ],
			'offers'                 => [
				'@type'         => 'Offer',
				'price'         => (string) config('seo.software.offer.price', '0'),
				'priceCurrency' => config('seo.software.offer.currency', 'USD'),
				'availability'  => config('seo.software.offer.availability', 'https://schema.org/InStock'),
			],
		];

		$subcategory = config('seo.software.application_subcategory');
		if ( $subcategory )
			$node['applicationSubCategory'] = $subcategory;

		$description = $this->localized('seo.software_description');
		if ( $description )
			$node['description'] = $description;

		$features = $this->localizedList('seo.software_features');
		if ( !empty($features) )
			$node['featureList'] = $features;

		$screenshot = $this->absUrl(config('general.default_og_image', ''));
		if ( $screenshot )
			$node['screenshot'] = $screenshot;

		array_push($this->data['@graph'], $node);

		return $this;
	}

	protected function addOrganization(): self {
		$base     = rtrim(url('/'), '/');
		$settings = app(SiteSettingsService::class);

		// Микроразметке нужно квадратное начертание, а загруженный оператором логотип
		// горизонтальный — поэтому его берём только когда он действительно заменён.
		$logo = $this->absUrl($settings->hasCustomImage('main_logo_dark')
			? $settings->imageUrl('main_logo_dark')
			: config('seo.org_logo', '/brand-assets/symbol_dark_theme.svg'));

		$node = [
			'@type'  => 'Organization',
			'@id'    => url('/#organization'),
			'name'   => $this->siteName(),
			'url'    => $base,
			'logo'   => [
				'@type' => 'ImageObject',
				'@id'   => url('/#logo'),
				'url'   => $logo,
			],
			'image'  => [ '@id' => url('/#logo') ],
		];

		$description = $this->localized('seo.org_description');
		if ( $description )
			$node['description'] = $description;

		$email = config('seo.org_email');
		if ( $email ) {
			$node['email'] = $email;
			$node['contactPoint'] = [[
				'@type'             => 'ContactPoint',
				'contactType'       => 'customer support',
				'email'             => $email,
				'availableLanguage' => (array) config('seo.org_languages', ['English']),
			]];
		}

		$sameas = array_values(array_filter((array) config('seo.org_sameas', [])));
		if ( !empty($sameas) )
			$node['sameAs'] = $sameas;

		array_push($this->data['@graph'], $node);

		return $this;
	}

	/**
	 * Схема основной навигации (SiteNavigationElement в составе ItemList) —
	 * помогает Google понять структуру сайта и повышает шанс на sitelinks.
	 * Состав пунктов задаёт хост в конфиге: маршруты публичной части пакету
	 * неизвестны.
	 */
	protected function addSiteNavigation(): self {
		$locale = app()->getLocale();
		$nav_bases = (array) config('seo.sitenav_routes', []);

		$items = [];
		$position = 1;

		foreach ($nav_bases as $base) {
			try {
				$url = loc_route($base, $locale);
			} catch (\Throwable) {
				continue; // маршрут не заведён для локали — пропускаем
			}

			$seo = app(SeoService::class)->getRouteSeoData($base);
			$name = !empty($seo['page_name']) ? $seo['page_name'] : $this->humanize($base);

			$items[] = [
				'@type'    => 'SiteNavigationElement',
				'position' => $position++,
				'name'     => $name,
				'url'      => $url,
			];
		}

		if ( empty($items) )
			return $this;

		array_push($this->data['@graph'], [
			'@type'           => 'ItemList',
			'@id'             => url('/#sitenav'),
			'name'            => 'Main navigation',
			'itemListElement' => $items,
		]);

		return $this;
	}

	/**
	 * Читаемое имя из базового имени маршрута (fallback, если нет page_name в SeoMeta).
	 */
	protected function humanize(string $route_base): string {
		$last = str_contains($route_base, '.') ? substr(strrchr($route_base, '.'), 1) : $route_base;
		return ucwords(str_replace(['-', '_'], ' ', $last));
	}

	/**
	 * Абсолютный URL из относительного пути (или как есть, если уже абсолютный).
	 */
	protected function absUrl(?string $path): string {
		if ( empty($path) )
			return '';
		if ( str_starts_with($path, 'http://') || str_starts_with($path, 'https://') )
			return $path;
		return rtrim(url('/'), '/') . '/' . ltrim($path, '/');
	}

	/**
	 * Значение локализованного конфига (['en'=>..., 'uk'=>...]) для текущей
	 * локали, с фолбэком на en → первый доступный. Строку возвращает как есть.
	 */
	protected function localized(string $config_key): string {
		$value = config($config_key);

		if ( is_array($value) ) {
			$locale = app()->getLocale();
			return (string) ($value[$locale] ?? $value['en'] ?? (reset($value) ?: ''));
		}

		return (string) ($value ?? '');
	}

	/**
	 * Локализованный список из конфига. Поддерживает две формы:
	 *  - локализованный: ['en'=>[...], 'uk'=>[...]] — берётся текущая локаль
	 *    с фолбэком на en → первый доступный набор;
	 *  - плоский список: [...] — возвращается как есть.
	 */
	protected function localizedList(string $config_key): array {
		$value = config($config_key, []);

		if ( !is_array($value) || empty($value) )
			return [];

		// Плоский список (значения — строки) — вернуть как есть.
		if ( array_is_list($value) && !is_array(reset($value)) )
			return array_values($value);

		$locale = app()->getLocale();
		$list = $value[$locale] ?? $value['en'] ?? reset($value);

		return is_array($list) ? array_values($list) : [];
	}

}
