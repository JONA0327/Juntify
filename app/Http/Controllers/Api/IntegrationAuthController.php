<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IntegrationAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required_without:username|nullable|email',
            'username' => 'required_without:email|nullable|string',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = null;

        if (!empty($validated['email'])) {
            $user = User::where('email', $validated['email'])->first();
        } elseif (!empty($validated['username'])) {
            $user = User::where('username', $validated['username'])->first();
        }

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        do {
            $plainToken = ApiToken::generatePlainTextToken();
            $tokenHash = ApiToken::hashToken($plainToken);
        } while (ApiToken::where('token_hash', $tokenHash)->exists());

        $token = $user->apiTokens()->create([
            'name' => $validated['device_name'] ?? 'Panel de integraciones',
            'token_hash' => $tokenHash,
            'abilities' => [
                'meetings:read',
                'tasks:read',
                'users:search',
            ],
            'last_used_at' => now(),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'abilities' => $token->abilities,
            'created_at' => $token->created_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->roles,
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->roles,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('apiToken');

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Token de acceso revocado correctamente.',
        ]);
    }
}
