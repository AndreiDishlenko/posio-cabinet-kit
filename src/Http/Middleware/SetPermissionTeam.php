<?php

namespace Posio\AdminKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeam
{
    /**
     * Scope every Spatie permission check to the user's current account.
     * Roles are stored per account (team_id = account_id), so the team id
     * must be set before any `can:`/role gate runs. Register this right
     * after 'auth' in any route group that checks permissions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->currentAccount();

        if ($account) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($account->id);
        }

        return $next($request);
    }
}
