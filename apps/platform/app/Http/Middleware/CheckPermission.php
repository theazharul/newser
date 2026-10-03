<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a permission name (e.g. 'permission:posts.create').
 * Runs after auth:sanctum, so an unauthenticated request is already a 401 —
 * authentication always precedes authorization (AC-9).
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! $user->hasPermissionTo($permission)) {
            throw new AuthorizationException('This action is forbidden.');
        }

        return $next($request);
    }
}
