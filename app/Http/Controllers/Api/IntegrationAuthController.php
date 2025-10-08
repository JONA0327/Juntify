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
    /**
     * @return array{plain: string, token: ApiToken}
     */
    private function issueTokenForUser(User $user, ?string $deviceName = null): array
    {
        do {
            $plainToken = ApiToken::generatePlainTextToken();
            $tokenHash = ApiToken::hashToken($plainToken);
        } while (ApiToken::where('token_hash', $tokenHash)->exists());

        $token = $user->apiTokens()->create([
            'name' => $deviceName ?? 'Panel de integraciones',
            'token_hash' => $tokenHash,
            'abilities' => [
                'meetings:read',
                'tasks:read',
                'users:search',
            ],
            'last_used_at' => now(),
        ]);

        return [
            'plain' => $plainToken,
            'token' => $token,
        ];
    }

    private function passwordMatches(string $plainPassword, string $hashedPassword): bool
    {
        try {
            return Hash::check($plainPassword, $hashedPassword);
        } catch (\RuntimeException $exception) {
            $hashInfo = password_get_info($hashedPassword);

            if (($hashInfo['algo'] ?? 0) === 0) {
                throw $exception;
            }

            return password_verify($plainPassword, $hashedPassword);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'nullable|string',
            'email' => 'required_without_all:login,username|nullable|email',
            'username' => 'required_without_all:login,email|nullable|string',
            'password' => 'required|string|min:6',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = null;
        $errorField = 'login';

        if (!empty($validated['login'])) {
            $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
                ? 'email'
                : 'username';

            $user = User::where($field, $validated['login'])->first();
            $errorField = 'login';
        } elseif (!empty($validated['email'])) {
            $user = User::where('email', $validated['email'])->first();
            $errorField = 'email';
        } elseif (!empty($validated['username'])) {
            $user = User::where('username', $validated['username'])->first();
            $errorField = 'username';
        }

        if (!$user || !$this->passwordMatches($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                $errorField => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        $issuedToken = $this->issueTokenForUser($user, $validated['device_name'] ?? 'Panel de integraciones');

        return response()->json([
            'token' => $issuedToken['plain'],
            'token_type' => 'Bearer',
            'abilities' => $issuedToken['token']->abilities,
            'created_at' => $issuedToken['token']->created_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->roles,
            ],
        ], 201);
    }

    public function createFromSession(Request $request): JsonResponse
    {
        $user = $request->user('web');

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'error' => 'Token de autenticación requerido'
            ], 401);
        }

        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
        ]);

        $issuedToken = $this->issueTokenForUser($user, $validated['device_name'] ?? 'Panel de perfil');

        return response()->json([
            'token' => $issuedToken['plain'],
            'token_type' => 'Bearer',
            'abilities' => $issuedToken['token']->abilities,
            'created_at' => $issuedToken['token']->created_at?->toIso8601String(),
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
