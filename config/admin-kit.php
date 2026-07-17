<?php

return [

    // Host project's user model/table. AdminKit never assumes it owns auth —
    // it attaches accounts/roles on top of whatever User model already exists.
    'user_model' => \App\Models\User::class,
    'users_table' => 'users',
    // Column on the users table used by HasSettings for per-user preferences
    // (current_account, menu_groups, onboarding flags). Point this at an
    // existing jsonb column instead of running the bundled migration if the
    // host project already has one.
    'user_settings_column' => 'settings',

    // Route prefix + name prefix for every AdminKit route (routes/admin.php).
    'route_prefix' => 'admin',
    'route_name_prefix' => 'admin-kit.',

    // Middleware stack applied to the whole AdminKit route group, in order.
    // 'auth' must resolve against the host's own guard.
    'middleware' => ['web', 'auth'],

    // Per-account roles (Spatie Permission teams, team_id = account_id).
    // Keep in sync with database/seeders/AdminKitRolesSeeder.php.
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
                ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'mdi:view-dashboard-outline', 'route' => 'admin-kit.dashboard', 'permission' => null],
            ],
        ],
        [
            'label' => 'Administration',
            'children' => [
                ['id' => 'settings', 'label' => 'Settings', 'icon' => 'proicons:settings', 'route' => 'admin-kit.settings', 'permission' => null],
            ],
        ],
    ],

];
