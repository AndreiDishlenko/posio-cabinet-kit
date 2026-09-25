<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\CabinetKit;
use Posio\CabinetKit\Services\SeoService;
use Posio\CabinetKit\Services\SitemapLastModService;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap';

    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
		$sitemap = Sitemap::create();

		$routes_seo = app(SeoService::class)->getAllSeoData();

		$locales = config('general.locales') ?? [];

		// Один экземпляр на прогон: манифест сборки читается один раз.
		$lastmod_service = app(SitemapLastModService::class);
		app()->instance(SitemapLastModService::class, $lastmod_service);

        foreach($routes_seo as $seo_item) {
			if ( empty($seo_item['index']) )
				continue;

			$lastmod = $lastmod_service->resolve($seo_item);

			if ( empty($locales) || count($locales)<=1 )
				$sitemap = $this->addElement($sitemap, $seo_item->toArray(), $lastmod);

			if ( empty($seo_item['locale']) && count($locales)>1 )
				$sitemap = $this->addForAllLocales($sitemap, $seo_item->toArray(), $lastmod);

			if ( !empty($seo_item['locale']) && in_array($seo_item['locale'], $locales) )
				$sitemap = $this->addForOneOfLocales($sitemap, $seo_item->toArray(), $routes_seo, $lastmod);
        }

		// Страницы модулей (товары, категории) — по одной на запись, записей раздела SEO у них нет.
		foreach (app(CabinetKit::class)->sitemapProviders() as $provider)
			$provider($sitemap, $locales);

        $sitemap_path = public_path('sitemap.xml');
        $sitemap->writeToFile($sitemap_path);
        $this->prettyPrintXml($sitemap_path);

		$this->warnAboutPagesWithoutSeo($routes_seo);
		$this->warnAboutPagesWithoutSources($routes_seo);
    }

	// Без файла страницы в сборке дата изменения следит только за метой, и правка
	// текста страницы в карту сайта не попадёт.
	private function warnAboutPagesWithoutSources($routes_seo): void {
		$lastmod_service = app(SitemapLastModService::class);

		if ( !$lastmod_service->tracksPageSources() )
			return;

		$missing = collect($routes_seo)
			->filter(fn($seo_item) => !empty($seo_item['index']))
			->pluck('route_name')
			->unique()
			->reject(fn($route_name) => $lastmod_service->hasPageSources($route_name))
			->values();

		if ( $missing->isEmpty() )
			return;

		$this->warn('Pages not found in the site build (lastmod follows SEO meta only): ' . $missing->implode(', '));
	}

	// Страница без SEO-меты индексируется, но в карту сайта не попадает и выходит без
	// заголовка и описания — называем такие страницы вслух, а не теряем молча.
	private function warnAboutPagesWithoutSeo($routes_seo): void {
		$seo_service = app(SeoService::class);
		$described = collect($routes_seo)->pluck('route_name')->unique();

		$missing = collect(Route::getRoutes()->getRoutesByName())
			->keys()
			->filter(fn($name) => $seo_service->isLocalizedPublicRoute($name))
			->map(fn($name) => $seo_service->baseRouteName($name))
			->unique()
			->reject(fn($base_name) => $described->contains($base_name))
			->values();

		if ( $missing->isEmpty() )
			return;

		$this->warn('Public pages without SEO meta (indexed, but missing from sitemap): ' . $missing->implode(', '));
	}

    private function prettyPrintXml(string $path): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->load($path);
        $dom->save($path);
    }

	public function addElement($sitemap, $seo_item, $lastmod) {
		$freqObject = $this->getFrequencyObject( $seo_item['changeFrequency'] );

		// Одноязычный сайт хранит в записи базовое имя маршрута — URL строится
		// из него же, а не берётся строкой как есть.
		$sitemap->add(
			Url::create($this->routeUrl($seo_item['route_name']))
				->setLastModificationDate($lastmod)
				->setChangeFrequency( $freqObject )
				->setPriority( $seo_item['priority'] ?? '0.8' )
		);

		return $sitemap;
	}

	public function addForAllLocales($sitemap, $seo_item, $lastmod) {
		$locales = config('general.locales') ?? [];
		// Та же версия по умолчанию, что объявляет мета самой страницы.
		$x_default_url = app(SeoService::class)->languageSelectorUrl($seo_item['route_name']);

		$freqObject = $this->getFrequencyObject( $seo_item['changeFrequency'] );

		foreach ($locales as $locale) {
			$url_with_locale = loc_route($seo_item['route_name'], $locale);

			$sitemap_item = Url::create( $url_with_locale )
					->setLastModificationDate($lastmod)
					->setChangeFrequency($freqObject)
					->setPriority($seo_item['priority'] ?? '0.8');

			foreach ($locales as $locale2) {
				$sitemap_item->addAlternate( loc_route($seo_item['route_name'], $locale2), $locale2);
			}

			$sitemap_item->addAlternate($x_default_url, 'x-default');

			$sitemap->add($sitemap_item);
		}

		return $sitemap;
	}

	public function addForOneOfLocales($sitemap, $seo_item, $routes_seo, $lastmod) {
        $localized_seo_items = collect($routes_seo)->filter(function ($item) use ($seo_item) {
            return
				$item['route_name'] === $seo_item['route_name'] &&
				$item['id'] !== $seo_item['id'] &&
				$item['index']==1;
        });

		$freqObject = $this->getFrequencyObject( $seo_item['changeFrequency'] );
		$locale = $seo_item['locale'];
		$url_with_locale = loc_route($seo_item['route_name'], $locale);

		$sitemap_item = Url::create( $url_with_locale )
			->setLastModificationDate($lastmod)
			->setChangeFrequency($freqObject)
			->setPriority($seo_item['priority'] ?? '0.8');

		if ( count($localized_seo_items) ) {
			// Add ourself
			$sitemap_item->addAlternate($url_with_locale, $locale);

			// Add alternates
			$cluster_locales = [$locale];
			foreach ($localized_seo_items as $alternate_item) {
				$alternate_locale = $alternate_item['locale'];
				$sitemap_item->addAlternate( loc_route($alternate_item['route_name'], $alternate_locale), $alternate_locale);
				$cluster_locales[] = $alternate_locale;
			}

			// x-default → English version if present in cluster, otherwise first available locale
			$default_locale = $this->xDefaultLocale($cluster_locales);
			if ( $default_locale === $locale ) {
				$x_default_url = $url_with_locale;
			} else {
				$default_item = collect($localized_seo_items)->firstWhere('locale', $default_locale);
				$x_default_url = loc_route($default_item['route_name'], $default_locale);
			}
			$sitemap_item->addAlternate($x_default_url, 'x-default');
		}

		$sitemap->add($sitemap_item);

		return $sitemap;
	}

	// Язык x-default: названный сайтом, иначе английский, иначе первый из имеющихся.
	protected function xDefaultLocale(array $locales): ?string {
		$configured = (string) config('general.x_default_locale');

		if ( $configured !== '' && in_array($configured, $locales) )
			return $configured;

		return in_array('en', $locales) ? 'en' : ($locales[0] ?? null);
	}

	// Незарегистрированный маршрут не должен ронять генерацию всей карты сайта.
	protected function routeUrl(string $route_name): string {
		try {
			return loc_route($route_name);
		} catch (\Throwable) {
			return $route_name;
		}
	}

	public function getFrequencyObject( $text_description ) {
		switch ( $text_description ) {

			case 'daily':
				return Url::CHANGE_FREQUENCY_DAILY;
				break;

			case 'weekly':
				return Url::CHANGE_FREQUENCY_WEEKLY;
				break;

			case 'mounthly':
				return Url::CHANGE_FREQUENCY_MONTHLY;
				break;

			case 'yearly':
				return Url::CHANGE_FREQUENCY_YEARLY;
				break;

			default:
				return Url::CHANGE_FREQUENCY_MONTHLY;
				break;

		}
	}
}
