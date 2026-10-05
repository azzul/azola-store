<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
            'device_type' => ['required', 'in:desktop,android'],
        ]);

        $throttleKey = 'api-login:'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['message' => 'Email atau password salah.'], 422);
        }

        RateLimiter::clear($throttleKey);

        [$token, $plain] = ApiToken::issue($user, $data['device_name'], $data['device_type']);

        return response()->json([
            'token' => $plain,
            'device_type' => $token->device_type,
            'channel' => $token->channel(),
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'device' => ['name' => $token->name, 'type' => $token->device_type, 'channel' => $token->channel()],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => 'Perangkat keluar.']);
    }
}
