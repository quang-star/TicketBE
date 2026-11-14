<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  int|string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $allowedRoles = array_map('intval', $roles);
        if (!in_array((int) $user->role, $allowedRoles, true)) {
            return response()->json([
                'code' => 403,
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
