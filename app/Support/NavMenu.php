<?php

namespace App\Support;

use App\Models\NavVisit;
use Illuminate\Support\Facades\Auth;

class NavMenu
{
    /**
     * Menu items below Home, in their default order (also the tie-breaker).
     *
     * @var array<string, array{label: string, route: string, pattern: string}>
     */
    public const ITEMS = [
        'tasks' => ['label' => 'Kanban', 'route' => 'tasks.index', 'pattern' => 'tasks.*'],
        'reminders' => ['label' => 'Reminders', 'route' => 'reminders.index', 'pattern' => 'reminders.*'],
        'tags' => ['label' => 'Tags', 'route' => 'tags.index', 'pattern' => 'tags.*'],
        'pointy' => ['label' => 'Pointy', 'route' => 'pointy.index', 'pattern' => 'pointy.*'],
        'deploy' => ['label' => 'Deploy', 'route' => 'deploy.index', 'pattern' => 'deploy.*'],
        'analytics' => ['label' => 'Analytics', 'route' => 'analytics.index', 'pattern' => 'analytics.*'],
        'monitors' => ['label' => 'Server health', 'route' => 'monitors.index', 'pattern' => 'monitors.*'],
        'incidents' => ['label' => 'Incidents', 'route' => 'incidents.index', 'pattern' => 'incidents.*'],
    ];

    /**
     * Background endpoints that poll and must not count as a visit.
     *
     * @var list<string>
     */
    public const POLLING_ROUTES = ['deploy.state', 'deploy.active', 'analytics.live'];

    /**
     * The key of the menu item the given route name belongs to, if any.
     */
    public static function itemFor(?string $routeName): ?string
    {
        foreach (self::ITEMS as $key => $item) {
            if ($routeName !== null && ! in_array($routeName, self::POLLING_ROUTES, true) && fnmatch($item['pattern'], $routeName)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Menu items ordered by the signed-in user's most visited first; ties keep the default order.
     *
     * @return array<string, array{label: string, route: string, pattern: string}>
     */
    public static function sorted(): array
    {
        $visits = Auth::id() === null ? collect() : NavVisit::where('user_id', Auth::id())->pluck('visits', 'item');
        $keys = array_keys(self::ITEMS);
        usort($keys, fn (string $a, string $b) => ($visits[$b] ?? 0) <=> ($visits[$a] ?? 0));

        return array_replace(array_flip($keys), self::ITEMS);
    }
}
