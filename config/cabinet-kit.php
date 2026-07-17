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
