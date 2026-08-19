<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The Logs menu item used to open a placeholder page of the cabinet; it now
// links straight at the bundled log viewer. The href is absolute and carries no
// route name on purpose: an Inertia visit would render that plain page inside
// the modal frame, where the viewer resolves its own API base path wrongly.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_links')) {
            return;
        }

        DB::table('admin_links')
            ->where('name', 'Logs')
            ->where('route', 'cabinet-kit.logs')
            ->update([
                'link' => '/admin/log-viewer',
                'route' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_links')) {
            return;
        }

        DB::table('admin_links')
            ->where('name', 'Logs')
            ->where('link', '/admin/log-viewer')
            ->update([
                'link' => null,
                'route' => 'cabinet-kit.logs',
            ]);
    }
};
