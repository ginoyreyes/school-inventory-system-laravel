<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use LogsActivity;

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = strtolower(trim($request->username));
        $user = User::where(str_contains($identifier, '@') ? 'email' : 'username', $identifier)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect username or password.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('spa-token')->plainTextToken;

        $this->logActivity($request, 'Logged in', 'User', (string) $user->id, $user->username, "Signed in as {$user->role}");

        return response()->json([
            'message' => 'Welcome back, ' . explode(' ', $user->name)[0] . '.',
            'token'   => $token,
            'user'    => new UserResource($user),
        ]);
    }

    public function signup(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:80'],
            'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $base = preg_replace('/[^a-z0-9._-]/', '', strtolower(explode('@', $data['email'])[0])) ?: 'user';
        $username = strlen($base) >= 3 ? substr($base, 0, 24) : 'usr-' . $base;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $suffix++;
            $username = substr($base, 0, 24) . $suffix;
        }

        $user = User::create([
            'name'      => trim($data['name']),
            'username'  => $username,
            'email'     => strtolower(trim($data['email'])),
            'role'      => 'Viewer',
            'is_active' => true,
            'password'  => $data['password'],
        ]);

        $token = $user->createToken('spa-token')->plainTextToken;
        $this->logActivity($request, 'Signed up for an account', 'User', (string) $user->id, $user->username, 'Self-registered as Viewer');

        return response()->json([
            'message' => 'Your account was created with read-only (Viewer) access.',
            'token'   => $token,
            'user'    => new UserResource($user),
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function logout(Request $request)
    {
        $this->logActivity($request, 'Logged out', 'User', (string) $request->user()->id, $request->user()->username);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'You have been logged out.']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'min:2', 'max:80'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (isset($data['name'])) $user->name = trim($data['name']);
        if (array_key_exists('email', $data)) $user->email = ! empty($data['email']) ? strtolower(trim($data['email'])) : null;
        $user->save();

        $this->logActivity($request, 'Updated own profile', 'User', (string) $user->id, $user->username);
        return response()->json(['message' => 'Profile updated.', 'user' => new UserResource($user)]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'password'        => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json(['message' => 'Your current password is incorrect.'], 400);
        }

        $user->update(['password' => $data['password'], 'password_changed_at' => now()]);
        $this->logActivity($request, 'Changed password', 'User', (string) $user->id, $user->username);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}