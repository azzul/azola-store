<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi perangkat (Azola Pos desktop / Android) lewat header "Authorization: Bearer <token>".
 * Token disimpan sebagai hash SHA-256; token mentah hanya tampil sekali saat dibuat.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findByPlain($plain) : null;

        if (! $token || ! $token->user || ! $token->user->isStaff()) {
            return response()->json(['message' => 'Token tidak valid atau sudah dicabut.'], 401);
        }

        // Hindari tulis database di setiap request: cukup perbarui paling sering sekali per menit.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
