<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Posio\CabinetKit\Models\SeoMeta;
use Symfony\Component\Process\Process;

// Дата изменения страницы для карты сайта. Поисковик доверяет ей, только пока она
// меняется вместе с содержанием, поэтому дата сдвигается лишь при расхождении отпечатка
// страницы с запомненным, а не при каждой генерации карты.
class SitemapLastModService {

	// Мета, которую видит поисковик и посетитель. Настройки индексации и карты сайта
	// содержанием не считаются.
	protected const META_FIELDS = [
		'page_name', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
		'og_image', 'og_title', 'og_description',
		'twitter_image', 'twitter_title', 'twitter_description',
		'jsonld_add_software', 'jsonld_add_organization',
	];

	protected ?array $build_manifest = null;
	protected array $sources_by_route = [];

	public function resolve(SeoMeta $seo_item): Carbon {
		$fingerprint = $this->fingerprint($seo_item);

		if ( $seo_item->content_fingerprint === $fingerprint && $seo_item->content_updated_at )
			return $seo_item->content_updated_at;

		// Первый отпечаток сравнить не с чем — берём дату последней известной правки,
		// а не сегодняшнюю, иначе все страницы разом объявятся свежими.
		$content_updated_at = $seo_item->content_fingerprint
			? now()
			: $this->lastKnownChange($seo_item);

		// В обход модели: дата правки меты должна остаться датой правки оператора.
		DB::table('seo_meta')->where('id', $seo_item->id)->update([
			'content_fingerprint' => $fingerprint,
			'content_updated_at'  => $content_updated_at,
		]);

		$seo_item->content_fingerprint = $fingerprint;
		$seo_item->content_updated_at = $content_updated_at;

		return $content_updated_at;
	}

	// Есть ли у маршрута файл страницы в сборке. Без него дата следит только за метой.
	public function hasPageSources(string $route_name): bool {
		return !empty($this->pageSources($route_name));
	}

	// Источники страниц не заданы — предупреждать о страницах без них бессмысленно.
	public function tracksPageSources(): bool {
		return $this->pagesDir() !== '';
	}

	protected function fingerprint(SeoMeta $seo_item): string {
		$meta = collect(self::META_FIELDS)->mapWithKeys(fn($field) => [$field => (string) $seo_item->$field]);

		$sources = collect($this->pageSources($seo_item->route_name))
			->mapWithKeys(fn($path) => [$path => sha1_file(base_path($path))]);

		return sha1(json_encode([$meta, $sources]));
	}

	protected function lastKnownChange(SeoMeta $seo_item): Carbon {
		$sources = $this->pageSources($seo_item->route_name);

		$meta_time = $seo_item->updated_at?->getTimestamp() ?? 0;
		$latest = max($meta_time, $this->lastCommitTime($sources) ?? $this->lastFileTime($sources));

		return $latest ? Carbon::createFromTimestamp($latest, config('app.timezone')) : now();
	}

	// Время файла на сервере — это время выкладки, а не правки: клон репозитория ставит
	// всем файлам одну дату. История коммитов точнее, время файла — запасной вариант.
	protected function lastCommitTime(array $sources): ?int {
		if ( empty($sources) )
			return null;

		// Под веб-сервером запуск процессов бывает запрещён, а git недоступен — тогда дата
		// берётся по файлам, генерация карты из-за этого падать не должна.
		try {
			$process = new Process(['git', 'log', '-1', '--format=%ct', '--', ...$sources], base_path());
			$process->run();
		} catch (\Throwable $e) {
			return null;
		}

		$time = (int) trim($process->getOutput());

		return $process->isSuccessful() && $time ? $time : null;
	}

	protected function lastFileTime(array $sources): int {
		return collect($sources)->map(fn($path) => filemtime(base_path($path)))->max() ?? 0;
	}

	// Файлы содержания страницы по графу импортов из манифеста сборки сайта.
	protected function pageSources(string $route_name): array {
		if ( array_key_exists($route_name, $this->sources_by_route) )
			return $this->sources_by_route[$route_name];

		$manifest = $this->buildManifest();
		$page_key = $this->pageEntryKey($route_name, $manifest);
		$sources = [];

		if ( $page_key ) {
			$pending = [$page_key];
			$visited = [];

			while ( $pending ) {
				$key = array_pop($pending);
				if ( isset($visited[$key]) )
					continue;
				$visited[$key] = true;

				$src = $manifest[$key]['src'] ?? null;
				if ( $src && ($key === $page_key || $this->isContentFile($src)) && is_file(base_path($src)) )
					$sources[] = $src;

				foreach ( $manifest[$key]['imports'] ?? [] as $import )
					$pending[] = $import;
			}

			sort($sources);
		}

		return $this->sources_by_route[$route_name] = $sources;
	}

	// Страница маршрута: сегменты имени — каталоги с заглавной, последний — файл
	// с заглавной или как есть.
	protected function pageEntryKey(string $route_name, array $manifest): ?string {
		$pages_dir = $this->pagesDir();
		if ( $pages_dir === '' )
			return null;

		$segments = explode('.', $route_name);
		$page = array_pop($segments);
		$dir = implode('/', array_map('ucfirst', $segments));

		foreach ( [ucfirst($page), $page] as $page_name ) {
			$key = $pages_dir . ($dir ? "$dir/" : '') . "$page_name.vue";
			if ( isset($manifest[$key]) )
				return $key;
		}

		return null;
	}

	protected function isContentFile(string $src): bool {
		foreach ( (array) config('seo.sitemap_lastmod.content_dirs', []) as $dir )
			if ( $dir !== '' && str_starts_with($src, rtrim($dir, '/') . '/') )
				return true;

		return false;
	}

	protected function pagesDir(): string {
		$dir = trim((string) config('seo.sitemap_lastmod.pages_dir', ''));

		return $dir === '' ? '' : rtrim($dir, '/') . '/';
	}

	protected function buildManifest(): array {
		if ( $this->build_manifest !== null )
			return $this->build_manifest;

		if ( $this->pagesDir() === '' )
			return $this->build_manifest = [];

		foreach ( (array) config('seo.sitemap_lastmod.manifests', ['build/manifest.json']) as $path ) {
			$manifest = is_file(public_path($path)) ? json_decode(file_get_contents(public_path($path)), true) : null;
			if ( is_array($manifest) && $this->containsSitePages($manifest) )
				return $this->build_manifest = $manifest;
		}

		return $this->build_manifest = [];
	}

	protected function containsSitePages(array $manifest): bool {
		foreach ( array_keys($manifest) as $key )
			if ( str_starts_with($key, $this->pagesDir()) )
				return true;

		return false;
	}
}
