<?php

namespace Posio\CabinetKit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $files = collect(File::glob(storage_path('logs/*.log')) ?: [])
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => File::size($path),
                'modified_at' => date('Y-m-d H:i:s', File::lastModified($path)),
            ])
            ->sortByDesc('modified_at')
            ->values();

        $selected = $request->query('file', $files->first()['name'] ?? null);

        return Inertia::render('pages/Admin/Logs', [
            'files' => $files,
            'selected' => $selected,
            'content' => $selected ? $this->tailLog($selected) : '',
        ]);
    }

    protected function tailLog(string $name, int $bytes = 200000): string
    {
        $path = storage_path('logs/'.basename($name));
        if (! File::isFile($path)) {
            return '';
        }

        $size = File::size($path);
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return '';
        }

        fseek($handle, max(0, $size - $bytes));
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }
}
