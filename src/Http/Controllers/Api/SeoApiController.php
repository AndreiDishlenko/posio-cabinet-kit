<?php

namespace Posio\CabinetKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Posio\CabinetKit\Models\SeoMeta;
use Posio\CabinetKit\Repositories\SeoRepository;

class SeoApiController extends Controller
{
    public function get(Request $request) {
		$result = app(SeoRepository::class)->getAll();

		return $result;
	}

	public function update(Request $request) {
		$validated = $request->validate([
            'route_name'    		=> 'required|string',
			'page_name'    			=> 'required|string',
			'locale'    			=> 'nullable|string',
			'index'    				=> 'required|boolean',
			'is_published'			=> 'nullable|boolean',
			'canonical_url'    		=> 'nullable|string',
			'meta_title'    		=> 'nullable|string',
			'meta_keywords'    		=> 'nullable|string',
			'meta_description'		=> 'nullable|string',
			'changeFrequency'		=> 'required|string',
			'priority'				=> 'required|numeric',
			// Open Graph
			'og_image'				=> 'nullable|string',
			'og_title'				=> 'nullable|string',
			'og_description'		=> 'nullable|string',
			// Twitter Card
			'twitter_image'			=> 'nullable|string',
			'twitter_title'			=> 'nullable|string',
			'twitter_description'	=> 'nullable|string',
			// JSON-LD
			'jsonld_add_software'		=> 'nullable|boolean',
			'jsonld_add_organization'	=> 'nullable|boolean',
        ]);

		if ( empty($request->id) ) {
			$result = app(SeoRepository::class)->create($validated);
		} else {
            $request->validate(['id'    =>  'required|numeric']);
			app(SeoRepository::class)->update($request->id, $validated);
			$result = SeoMeta::find($request->id);
		}

		return response()->json($result);
	}

	public function delete(Request $request) {
        $request->validate([
            'id'            =>  'required|numeric',
        ]);

        app(SeoRepository::class)->deleteEntity($request->id);

        return response(['status' => 'ok']);
    }

    public function restore(Request $request) {
        $request->validate([
            'id'            =>  'required|numeric',
        ]);

        app(SeoRepository::class)->restoreEntity($request->id);

        return response(['status' => 'ok']);
    }

	public function createSitemaps(Request $request) {
        Artisan::call('sitemap:generate');

        return response(['status' => 'ok']);
	}

	/**
	 * Черновик меты (page_name, meta_title, meta_description) от языковой модели.
	 *
	 * Собственной языковой модели у пакета нет — генератор подключает хост,
	 * указав в конфиге класс с методом generate(array): array. Пока он не задан,
	 * кнопка в карточке отвечает понятной ошибкой, а не молчит.
	 */
	public function generateMeta(Request $request) {
		$validated = $request->validate([
			'route_name' => 'required|string',
			'locale'     => 'required|string',
			'page_name'  => 'nullable|string',
			'context'    => 'nullable|string',
		]);

		$generator = config('cabinet-kit.seo.meta_generator');

		if ( !$generator || !class_exists($generator) )
			return response()->json([
				'message' => 'AI meta generation is not configured (cabinet-kit.seo.meta_generator).',
			], 422);

		try {
			$result = app($generator)->generate($validated);
		} catch (\Throwable $e) {
			return response()->json([
				'message' => $e->getMessage(),
			], 422);
		}

		return response()->json([
			'page_name'        => $result['page_name']        ?? '',
			'meta_title'       => $result['meta_title']       ?? '',
			'meta_keywords'    => $result['meta_keywords']    ?? '',
			'meta_description' => $result['meta_description'] ?? '',
		]);
	}
}
