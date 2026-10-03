<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Abort with 403 unless the authenticated user has the given role (e.g. "role:employer").
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requiredRole = UserRole::from($role);

        abort_unless(
            $request->user()?->role === $requiredRole,
            403,
            "Ta funkcja jest dostępna tylko dla kont {$requiredRole->audienceLabel()}.",
        );

        return $next($request);
    }
}
