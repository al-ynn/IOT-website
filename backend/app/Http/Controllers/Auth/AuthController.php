<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $user = User::where('email', $credentials['email'])->first();
        abort_unless($user && Hash::check($credentials['password'], $user->password), 422, 'Invalid credentials.');
        abort_unless($user->isActive(), 403, 'Account is inactive.');
        abort_if($user->organization && $user->organization->status !== 'active', 403, 'Organization is inactive.');

        return $this->session($user);
    }

    public function me(Request $request)
    {
        return $this->user($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function session(User $user): array
    {
        return ['token' => $user->createToken('web')->plainTextToken, 'user' => $this->user($user)];
    }

    private function user(User $user): array
    {
        return (new AuthUserResource($user->fresh()))->resolve();
    }
}
