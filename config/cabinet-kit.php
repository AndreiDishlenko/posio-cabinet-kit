<?php

return [

    // Host project's user model/table.
    'user_model' => \App\Models\User::class,
    'users_table' => 'users',
    // Column on the users table used by HasSettings for per-user preferences
    // (current_account, menu_groups, onboarding flags). Point this at an
    // existing jsonb column instead of running the bundled migration if the
    // host project already has one.
    'user_settings_column' => 'settings',

    // Route prefix + name prefix for every CabinetKit route (routes/cabinet.php).
    // The bundled auth routes (login/register/logout/password reset/email
    // verification) live under the same URL prefix but keep Laravel's own
    // unprefixed route names (login, register, ...) so framework internals
    // (auth middleware redirects, signed verification links) resolve them.
    'route_prefix' => 'cabinet',
    'route_name_prefix' => 'cabinet-kit.',

    // Middleware stack applied to the authenticated CabinetKit route group,
    // in order. 'auth' must resolve against the host's own guard.
    'middleware' => ['web', 'auth'],

    // Landing pages of the auth flow moved to config/cabinet-kit-redirects.php.

    // Blade root view every CabinetKit Inertia page renders into. The bundled
    // view (resources/views/app.blade.php in the package) prints @routes,
    // @vite(vite_entry) and @inertia. Point this at your own view to take
    // full control of the cabinet's HTML shell.
    'root_view' => 'cabinet-kit::app',

    // Register bundled auth routes (login/register/password/email verify).
    // Set to false when the host application already owns these route names.
    'auth_routes' => true,

    // Social sign-in through Laravel Socialite (requires laravel/socialite, plus
    // socialiteproviders/apple for Apple). These credentials are bridged into
    // config('services.*') at boot unless the host already defines them there,
    // so a consumer only has to fill in the env vars. A provider left without a
    // client id answers 404: its routes stay registered either way so the
    // sign-in page keeps resolving their URLs. Leave `redirect` empty to use
    // the bundled callback route under `route_prefix`.
    'social_auth' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
        ],
        'apple' => [
            'client_id' => env('APPLE_CLIENT_ID'),
            // Apple has no static secret: the driver signs a short-lived one
            // from the key/team identifiers and the private key below.
            'client_secret' => '',
            'key_id' => env('APPLE_KEY_ID'),
            'team_id' => env('APPLE_TEAM_ID'),
            'private_key' => env('APPLE_PRIVATE_KEY'),
            'redirect' => env('APPLE_REDIRECT_URI'),
        ],
    ],

    // Vite entry the bundled root view loads. Must also be listed in the
    // host vite.config.js `input` array. cabinet-kit:install scaffolds it.
    'vite_entry' => 'resources/_admin/js/cabinet.ts',

    // Host override components. Paths are relative to resource_path().
    'overrides_path' => '_admin/overrides',

    // JSON translations exposed to Vue as $t(). By default CabinetKit reads
    // the host's Laravel JSON file: lang/{locale}.json.
    'translations' => [
        'json_paths' => [lang_path()],
        'fallback_locale' => null,
        'locales' => [
            'uk' => [
                'name' => 'Ukrainian',
                'icon' => 'emojione:flag-for-ukraine',
            ],
            'en' => [
                'name' => 'English',
                'icon' => 'emojione:flag-for-united-kingdom',
            ],
        ],
    ],

    // Built-in users created during installation. Existing users are kept as-is:
    // passwords are only written when the user row is first created.
    //
    // Their passwords are listed here, and are therefore the same in every
    // project built on this package: an account still holding the one written
    // below is led to the password form and nowhere else until it picks its
    // own. Turn that off only when those passwords are managed outside the
    // application.
    'force_system_password_change' => true,
    'system_team_id' => 0,
    'system_users' => [
        'sa' => [
            'name' => 'sa',
            'email' => env('CABINET_KIT_SA_EMAIL', 'sa@gmail.com'),
            'password' => env('CABINET_KIT_SA_PASSWORD', '12345678'),
            'system_role' => 'SAdmin',
            'account_name' => 'Root Account',
        ],
        'admin' => [
            'name' => 'admin',
            'email' => env('CABINET_KIT_ADMIN_EMAIL', 'admin@gmail.com'),
            'password' => env('CABINET_KIT_ADMIN_PASSWORD', '12345678'),
            'system_role' => 'System administrator',
            'account_name' => 'Admin Account',
        ],
    ],

    // Per-account roles (Spatie Permission teams, team_id = account_id).
    // Keep in sync with database/seeders/CabinetKitRolesSeeder.php.
    'roles' => [
        'owner_role' => 'Account owner',
        'default_member_role' => 'Administrator',
        'assignable_roles' => ['Administrator', 'Manager', 'User'],
    ],

    // Where the bundled log viewer (opcodesio/log-viewer) is mounted. CabinetKit
    // writes this into the viewer's own runtime config, which is never published
    // — unless you publish config/log-viewer.php, and then that file wins and
    // this key is ignored. Access is gated by the `sysper-log-view` system
    // permission. Keep the Logs menu item below pointing at the same path.
    'log_viewer' => [
        'route_path' => 'admin/log-viewer',
    ],

    // Side menu groups. Each item needs either a `route` name (Inertia visit)
    // or a `link` (plain href). `permission` gates visibility (null = always shown).
    'menu' => [
        [
            'label' => 'Administration',
            'children' => [
                ['id' => 'users', 'label' => 'Користувачі', 'icon' => 'ph:users', 'route' => 'cabinet-kit.users', 'permission' => 'sysper-users'],
                ['id' => 'permissions', 'label' => 'Дозволи', 'icon' => 'fluent-mdl2:permissions', 'route' => 'cabinet-kit.permissions', 'permission' => 'sysper-roles'],
                ['id' => 'permissions-account', 'label' => 'Ролі акаунту', 'icon' => 'fluent-mdl2:permissions', 'route' => 'cabinet-kit.permissions.account', 'permission' => 'sysper-roles'],
                // Plain href on purpose: the log viewer is not an Inertia page,
                // and an Inertia visit would render it inside the modal frame
                // instead of navigating there. Path mirrors `log_viewer` above.
                ['id' => 'logs', 'label' => 'Logs', 'icon' => 'ix:log', 'link' => '/admin/log-viewer', 'permission' => 'sysper-log-view'],
                ['id' => 'settings', 'label' => 'Settings', 'icon' => 'proicons:settings', 'route' => 'cabinet-kit.settings', 'permission' => null],
            ],
        ],
    ],

];
