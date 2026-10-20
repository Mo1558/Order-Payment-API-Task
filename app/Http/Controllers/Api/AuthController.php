<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $token = auth('api')->login($user);

        return $this->sendCreated([
            'user' => new UserResource($user),
            'authorization' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ],
        ], 'User successfully registered.');
    }

    /**
     * Authenticate user and return JWT token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return $this->sendError('Invalid email or password.', [], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        return $this->sendResponse([
            'user' => new UserResource($user),
            'authorization' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ],
        ], 'Login successful.');
    }

    /**
     * Get authenticated user profile.
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        return $this->sendResponse(new UserResource($user), 'User profile retrieved successfully.');
    }

    /**
     * Refresh JWT token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = auth('api')->refresh();

            return $this->sendResponse([
                'user' => new UserResource(auth('api')->user()),
                'authorization' => [
                    'token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 60,
                ],
            ], 'Token refreshed successfully.');
        } catch (\Throwable $e) {
            return $this->sendError('Could not refresh token: '.$e->getMessage(), [], 401);
        }
    }

    /**
     * Log out authenticated user (invalidate token).
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->sendResponse(null, 'Successfully logged out.');
    }
}
