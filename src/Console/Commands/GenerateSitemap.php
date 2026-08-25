<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Posio\CabinetKit\Services\SeoService;
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

        foreach($routes_seo as $seo_item) {
			if ( empty($seo_item['index']) )
				continue;

			if ( empty($locales) || count($locales)<=1 )
				$sitemap = $this->addElement($sitemap, $seo_item->toArray());

			if ( empty($seo_item['locale']) && count($locales)>1 )
				$sitemap = $this->addForAllLocales($sitemap, $seo_item->toArray());

			if ( !empty($seo_item['locale']) && in_array($seo_item['locale'], $locales) )
				$sitemap = $this->addForOneOfLocales($sitemap, $seo_item->toArray(), $routes_seo);
        }

        $sitemap_path = public_path('sitemap.xml');
        $sitemap->writeToFile($sitemap_path);
        $this->prettyPrintXml($sitemap_path);
    }

    private function prettyPrintXml(string $path): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->load($path);
        $dom->save($path);
    }

	public function addElement($sitemap, $seo_item) {
		$freqObject = $this->getFrequencyObject( $seo_item['changeFrequency'] );

		// Одноязычный сайт хранит в записи базовое имя маршрута — URL строится
		// из него же, а не берётся строкой как есть.
		$sitemap->add(
			Url::create($this->routeUrl($seo_item['route_name']))
				->setLastModificationDate(now())
				->setChangeFrequency( $freqObject )
				->setPriority( $seo_item['priority'] ?? '0.8' )
		);

		return $sitemap;
	}

	public function addForAllLocales($sitemap, $seo_item) {
		$locales = config('general.locales') ?? [];
		$default_locale = in_array('en', $locales) ? 'en' : ($locales[0] ?? null);
		$x_default_url = loc_route($seo_item['route_name'], $default_locale);

		$freqObject = $this->getFrequencyObject( $seo_item['changeFrequency'] );

		foreach ($locales as $locale) {
			$url_with_locale = loc_route($seo_item['route_name'], $locale);

			$sitemap_item = Url::create( $url_with_locale )
					->setLastModificationDate(now())
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

	public function addForOneOfLocales($sitemap, $seo_item, $routes_seo) {
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
			->setLastModificationDate(now())
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
			$default_locale = in_array('en', $cluster_locales) ? 'en' : $cluster_locales[0];
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
