<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Posio\CabinetKit\Services\SiteSettingsService;

// Переносит фирменные файлы установки из статики в настройки сайта. Нужен там, где
// раздел настроек появился позже самого бренда; на чистой установке не запускается —
// там действуют обезличенные заготовки.
class ImportSiteBrand extends Command
{
    protected $signature = 'site:import-brand';

    protected $description = 'Import the installation own logos and favicons into site settings';

    public function handle(SiteSettingsService $settings) : int {
        $imported = $settings->importLegacyBrand();

        if ( !$imported ) {
            $this->info('Nothing to import: every image is either already set or missing on disk.');

            return self::SUCCESS;
        }

        foreach ( $imported as $key => $path )
            $this->line("  {$key} → {$path}");

        $this->info(count($imported) . ' image(s) imported.');

        return self::SUCCESS;
    }
}
