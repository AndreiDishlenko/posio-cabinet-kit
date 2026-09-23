<?php

namespace Posio\CabinetKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\CabinetKit;
use Symfony\Component\HttpFoundation\Response;

/**
 * Единый эндпоинт справочников кабинета: сливает справочники модулей и хоста.
 *
 * Хост, у которого эндпоинт был своим (до модулей), не обязан его переносить:
 * его ответ берётся как есть и выигрывает при совпадении имён — кабинет
 * запрашивает справочники одним адресом, и этот адрес теперь пакетный.
 */
class DictionariesApiController extends Controller
{
    // Имена, под которыми хосты заводили свой эндпоинт справочников.
    protected const HOST_ROUTES = ['cabinet.api.dictionaries'];

    public function index(Request $request, CabinetKit $kit): JsonResponse
    {
        $dictionaries = [];

        foreach ($kit->dictionaryProviders() as $providers) {
            foreach ($providers as $provider) {
                $dictionaries = array_replace($dictionaries, (array) $provider($request));
            }
        }

        $dictionaries = array_replace($dictionaries, $this->hostDictionaries($request));

        // Точечное обновление после правки записи просит один справочник заголовком.
        $only = $request->header('X-only');

        if ($only) {
            return response()->json(array_key_exists($only, $dictionaries) ? [$only => $dictionaries[$only]] : []);
        }

        return response()->json($dictionaries);
    }

    // Стек кабинета уже пройден этим запросом, поэтому действие хоста
    // вызывается напрямую, без второго прохода через middleware.
    protected function hostDictionaries(Request $request): array
    {
        foreach (self::HOST_ROUTES as $name) {
            $route = Route::getRoutes()->getByName($name);

            if (! $route || $route->getActionName() === static::class.'@index') {
                continue;
            }

            $response = $route->bind($request)->run();

            if ($response instanceof JsonResponse) {
                return (array) $response->getData(true);
            }

            if ($response instanceof Response) {
                return (array) json_decode((string) $response->getContent(), true);
            }

            return (array) $response;
        }

        return [];
    }
}
