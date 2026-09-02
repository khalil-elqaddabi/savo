<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a mobile client and issue a personal access token.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        if (filled($user->two_factor_secret)) {
            throw ValidationException::withMessages([
                'two_factor_required' => [__('Two-factor authentication is enabled. Sign in on the web instead.')],
            ]);
        }

        $token = $user->createToken('savo-mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Register a new account from the mobile client.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['nullable', 'in:fr,ar,en'],
        ]);

        // CreateNewUser re-runs validation including the `confirmed` rule, so
        // the matching confirmation field must stay in the payload it receives.
        $user = app(CreateNewUser::class)->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_confirmation' => $request->string('password_confirmation')->toString(),
            'locale' => $data['locale'] ?? null,
        ]);

        event(new Registered($user));

        $token = $user->createToken('savo-mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    /**
     * Return the currently authenticated user.
     */
    public function user(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }
}