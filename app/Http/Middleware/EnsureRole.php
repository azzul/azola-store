<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Akun ini tidak punya akses ke fitur tersebut.'], 403);
            }

            abort(403, 'Akun ini tidak punya akses ke halaman tersebut.');
        }

        return $next($request);
    }
}
