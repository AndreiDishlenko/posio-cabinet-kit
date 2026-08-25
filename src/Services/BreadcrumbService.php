<?php

namespace Posio\CabinetKit\Services;

class BreadcrumbService {
    protected array $items = [];

    public static function make(): self {
        return new self();
    }

    public function add(string $route_name, array $route_params = []): self {
		// Routes may be named `{base}.{locale}` (e.g. downloads.en).
		// Strip the locale suffix for SEO lookup and URL generation.
		$base_name = preg_replace('/\.(' . $this->localePattern() . ')$/', '', $route_name);

		$seo_data = app(SeoService::class)->getRouteSeoData($base_name);

        $this->items[] = [
            'name'  => $seo_data->page_name ?? 'Default Page',
            'url'   => loc_route($base_name, null, $route_params),
        ];

        return $this;
    }

    public function get(): array {
        return $this->items;
    }

    protected function localePattern(): string {
        $locales = array_filter((array) config('general.locales', []), fn ($locale) => is_string($locale) && $locale !== '');

        if ( empty($locales) )
            return 'en';

        return implode('|', array_map(fn ($locale) => preg_quote($locale, '/'), $locales));
    }
}
