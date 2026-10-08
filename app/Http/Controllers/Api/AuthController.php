<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Emite un token personal (apps móviles, dispositivos de control de acceso). */
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        $key = 'api-login:'.mb_strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Intente más tarde.']);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            RateLimiter::hit($key);
            activity('auth')->event('failed')->withProperties(['email' => $data['email'], 'ip' => $request->ip(), 'channel' => 'api'])->log('Intento de inicio de sesión fallido (API)');

            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        RateLimiter::clear($key);
        $abilities = $user->canAccessAdmin() ? ['*'] : ['member'];
        $token = $user->createToken($data['device_name'], $abilities, now()->addDays(90));

        activity('auth')->causedBy($user)->event('login')->withProperties(['ip' => $request->ip(), 'channel' => 'api'])->log('Inicio de sesión (API)');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], 201);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
