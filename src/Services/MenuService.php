<?php

namespace Posio\CabinetKit\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Posio\CabinetKit\Models\AdminLink;

class MenuService
{
    /** Side menu filtered to what the authenticated user can actually see. */
    public function menuFor($user): array
    {
        if (Schema::hasTable('admin_links') && AdminLink::query()->where('is_published', 1)->exists()) {
            return $this->withResolvableRoutes($this->menuFromDatabase($user));
        }

        $groups = config('cabinet-kit.menu', []);
        $visibleGroups = [];
        foreach ($groups as $group) {
            $children = array_values(array_filter(
                $group['children'] ?? [],
                fn ($item) => empty($item['permission']) || $this->allowed($user, $item['permission']),
            ));

            if ($children) {
                $visibleGroups[] = ['label' => $group['label'], 'children' => $children];
            }
        }

        return $this->withResolvableRoutes($visibleGroups);
    }

    /**
     * An item naming a route this application does not register takes the whole
     * page down when the template resolves its address, so it is dropped here
     * instead of rendered. The diagnostics command reports what was dropped.
     */
    public function withResolvableRoutes(array $groups): array
    {
        $result = [];

        foreach ($groups as $group) {
            $children = array_values(array_filter(
                $group['children'] ?? [],
                fn ($item) => empty($item['route']) || Route::has($item['route']),
            ));

            if ($children) {
                $result[] = array_merge($group, ['children' => $children]);
            }
        }

        return $result;
    }

    public function currentPage(?string $routeName, $user): ?array
    {
        foreach ($this->menuFor($user) as $group) {
            foreach ($group['children'] ?? [] as $item) {
                if (($item['route'] ?? null) === $routeName) {
                    return [
                        'id' => $item['id'],
                        'name' => $item['label'] ?? null,
                        'section' => $group['label'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    protected function menuFromDatabase($user): array
    {
        $items = AdminLink::query()
            ->where('is_published', 1)
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();

        $result = [];
        $currentGroup = null;
        $children = [];

        foreach ($items as $item) {
            if (! $this->allowed($user, $item->permissions)) {
                continue;
            }

            if ($item->is_header) {
                if ($currentGroup && $children) {
                    $currentGroup['children'] = $children;
                    $result[] = $currentGroup;
                }

                $currentGroup = ['label' => $item->name, 'children' => []];
                $children = [];
                continue;
            }

            $child = [
                'id' => $item->id,
                'label' => $item->name,
                'icon' => $item->icon,
                'route' => $item->route,
                'link' => $item->link,
                'permission' => $item->permissions,
            ];

            if ($currentGroup) {
                $children[] = $child;
            } else {
                $result[] = ['label' => '', 'children' => [$child]];
            }
        }

        if ($currentGroup && $children) {
            $currentGroup['children'] = $children;
            $result[] = $currentGroup;
        }

        return $result;
    }

    protected function allowed($user, ?string $permission): bool
    {
        if (empty($permission)) {
            return true;
        }

        if (str_starts_with($permission, 'sysper-')) {
            return method_exists($user, 'canSystem') ? $user->canSystem($permission) : $user->can($permission);
        }

        return $user->can($permission);
    }
}
