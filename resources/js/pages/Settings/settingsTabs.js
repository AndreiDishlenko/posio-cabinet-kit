export function buildSettingsTabs(canManageAccount = false) {
    const tabs = [
        // { id: 'account', label: 'Account' },
        { id: 'profile', label: 'Profile' },
    ];

    if (canManageAccount) {
        tabs.splice(1, 0, { id: 'users', label: 'Users' });
    }

    return tabs;
}
