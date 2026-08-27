<?php

namespace Posio\CabinetKit\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// Картинки пакета отдаёт приложение, а не веб-сервер: при установке ничего не
// копируется в публичный каталог хоста, поэтому физического пути у них нет.
class PackageAssetController extends Controller
{
    public function cabinet(string $path): BinaryFileResponse
    {
        return $this->serve('cabinet-assets', $path);
    }

    // Обезличенные заготовки бренда: пока оператор не загрузил свои картинки,
    // значок вкладки и логотипы должны работать без шага публикации.
    public function brand(string $path): BinaryFileResponse
    {
        return $this->serve('brand-assets', $path);
    }

    // Запрошенный путь обязан остаться внутри своего каталога — иначе выход за
    // его пределы точками отдал бы любой файл на диске.
    protected function serve(string $bundle, string $path): BinaryFileResponse
    {
        $assetRoot = realpath(__DIR__.'/../../../public/'.$bundle);
        $assetPath = $assetRoot ? realpath($assetRoot.DIRECTORY_SEPARATOR.$path) : false;

        if (! $assetRoot || ! $assetPath || ! str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return response()->file($assetPath);
    }
}
