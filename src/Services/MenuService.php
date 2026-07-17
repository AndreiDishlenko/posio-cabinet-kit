<?php

namespace Posio\CabinetKit\Services;

class MenuService
{
    /** Side menu filtered to what the authenticated user can actually see. */
    public function menuFor($user): array
    {
        $groups = config('cabinet-kit.menu', []);

        $visibleGroups = [];
        foreach ($groups as $group) {
            $children = array_values(array_filter(
                $group['children'] ?? [],
                fn ($item) => empty($item['permission']) || $user->can($item['permission']),
            ));

            if ($children) {
                $visibleGroups[] = ['label' => $group['label'], 'children' => $children];
            }
        }

        return $visibleGroups;
    }

    /** Settings tabs filtered to what the authenticated user can actually see. */
    public function settingsTabsFor($user): array
    {
        return array_values(array_filter(
            config('cabinet-kit.settings_tabs', []),
            fn ($tab) => empty($tab['permission']) || $user->can($tab['permission']),
        ));
    }
}
