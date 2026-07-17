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

    // Named route to redirect to after a successful login or registration.
    'login_redirect_route' => 'cabinet-kit.dashboard',

    // Blade root view every CabinetKit Inertia page renders into. The bundled
    // view (resources/views/app.blade.php in the package) prints @routes,
    // @vite(vite_entry) and @inertia. Point this at your own view to take
    // full control of the cabinet's HTML shell.
    'root_view' => 'cabinet-kit::app',

    // Vite entry the bundled root view loads. Must also be listed in the
    // host vite.config.js `input` array. cabinet-kit:install scaffolds it.
    'vite_entry' => 'resources/_admin/js/admin.js',

    // Per-account roles (Spatie Permission teams, team_id = account_id).
    // Keep in sync with database/seeders/CabinetKitRolesSeeder.php.
    'roles' => [
        'owner_role' => 'Account owner',
        'default_member_role' => 'Administrator',
        'assignable_roles' => ['Administrator', 'Manager', 'User'],
    ],

    // Settings page tabs (component name resolved relative to
    // resources/js/pages/Settings/ — package tab first, then host override).
    // Add entries here to extend the Settings page without touching Vue files.
    'settings_tabs' => [
        ['id' => 'account', 'label' => 'Account', 'component' => 'AccountTab', 'permission' => null],
        ['id' => 'users', 'label' => 'Users', 'component' => 'UsersTab', 'permission' => 'manage-account'],
        ['id' => 'profile', 'label' => 'Profile', 'component' => 'ProfileTab', 'permission' => null],
    ],

    // Side menu groups. Each item needs either a `route` name (Inertia visit)
    // or a `link` (plain href). `permission` gates visibility (null = always shown).
    'menu' => [
        [
            'label' => 'Overview',
            'children' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'mdi:view-dashboard-outline', 'route' => 'cabinet-kit.dashboard', 'permission' => null],
            ],
        ],
        [
            'label' => 'Administration',
            'children' => [
                ['id' => 'settings', 'label' => 'Settings', 'icon' => 'proicons:settings', 'route' => 'cabinet-kit.settings', 'permission' => null],
            ],
        ],
    ],

];
