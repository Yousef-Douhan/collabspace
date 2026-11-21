<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Track;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'track' => $data['track'] ?? Track::BackEnd,
            'locale' => $data['locale'] ?? 'en',
            'joined_at' => now(),
        ]);

        return $this->tokenResponse($user, $data['device_name'] ?? null, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], $status);
    }
}
