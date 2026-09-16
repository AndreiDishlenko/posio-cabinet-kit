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
    // socialiteproviders/apple for Apple). Set a provider's `enabled` flag to
    // false to hide its form controls and make its endpoints answer 404. The
    // credentials are bridged into
    // config('services.*') at boot unless the host already defines them there,
    // so a consumer only has to fill in the env vars. A provider left without a
    // client id answers 404: its routes stay registered either way so the
    // sign-in page keeps resolving their URLs. Leave `redirect` empty to use
    // the bundled callback route under `route_prefix`.
    'social_auth' => [
        'google' => [
            'enabled' => env('GOOGLE_AUTH_ENABLED', true),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
        ],
        'apple' => [
            'enabled' => env('APPLE_AUTH_ENABLED', true),
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

    // Email confirmation and password reset letters, built from the package's
    // templates in the visitor's language instead of Laravel's stock English
    // ones. Customize from the host, nothing in vendor:
    // - texts: lang/vendor/cabinet-kit/{locale}/mail.php — only the keys you
    //   list there are replaced;
    // - templates: resources/views/vendor/cabinet-kit/mail/{verify-email,
    //   reset-password,layout}.blade.php are picked up automatically, or point
    //   `views` at any view of your own.
    // `php artisan vendor:publish --tag=cabinet-kit-mail` copies both as a start.
    // Set `enabled` to false to keep Laravel's letters or your own
    // VerifyEmail/ResetPassword::toMailUsing callbacks (those always win).
    'auth_mail' => [
        'enabled' => true,
        'views' => [
            'verify_email' => 'cabinet-kit::mail.verify-email',
            'reset_password' => 'cabinet-kit::mail.reset-password',
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
    // Every role named here is created on php artisan migrate, together with
    // the system roles, if it does not exist yet.
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

    // Brand and appearance an operator edits from the cabinet ("Site settings" /
    // "Cabinet settings"): site name, favicon, default theme, logos.
    //
    // `views` lists the host Blade views that receive $site_name, $site_favicon
    // and $site_theme through a view composer. The cabinet's own root view is
    // always covered; add the public site's layout view name here (e.g. 'main')
    // and print those variables in it — see docs/cabinet-kit/site-settings.md.
    //
    // The favicon/theme keys a view gets are chosen by `scopes`: the cabinet
    // scope for cabinet views, the main scope for everything else. A view mapped
    // to `null` gets the site name only — that is how an app whose own icon and
    // theme are part of the product itself opts out.
    'site' => [
        'views' => [
            'cabinet-kit::app' => 'cabinet',
        ],
        // Share the `site` Inertia prop (name + logos) on every Inertia response,
        // not only cabinet pages — the public site's header reads it too.
        'share_prop' => true,
    ],

    // Per-page SEO meta, Open Graph and JSON-LD for the host's public pages.
    // Content lives in the `seo_meta` table, edited in the cabinet's SEO section;
    // site-wide values (organization, navigation) live in config/seo.php.
    'seo' => [
        // Share the `seo` Inertia prop consumed by SeoMeta.vue. Turn off on a
        // project with no public site: nothing reads the prop there.
        'share_prop' => true,
        // Route name prefilled during installation, and the one JSON-LD treats
        // as the site's front page.
        'home_route' => 'home',
        // Class with a generate(array): array method behind the "Generate with
        // AI" button of the SEO card. Without it the button answers "not
        // configured" — the package ships no language model of its own.
        'meta_generator' => null,
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
            ],
        ],
    ],

];
