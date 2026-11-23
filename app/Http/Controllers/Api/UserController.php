<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        Gate::authorize('users:read');
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $query = User::query();
        if ($request->filled('role')) $query->where('role', $request->query('role'));
        if ($request->query('status') === 'Active') $query->where('is_active', true);
        if ($request->query('status') === 'Inactive') $query->where('is_active', false);
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('username', 'like', $s)->orWhere('email', 'like', $s));
        }

        $roleRows = User::selectRaw('role, COUNT(*) as count')->groupBy('role')->pluck('count', 'role');
        $counts = [
            'Administrator'   => (int) ($roleRows['Administrator'] ?? 0),
            'Inventory Staff' => (int) ($roleRows['Inventory Staff'] ?? 0),
            'Viewer'          => (int) ($roleRows['Viewer'] ?? 0),
        ];

        $paginated = $query->orderByDesc('created_at')->paginate($limit);

        return response()->json([
            'users'       => UserResource::collection($paginated->items()),
            'total'       => $paginated->total(),
            'page'        => $paginated->currentPage(),
            'pages'       => max(1, $paginated->lastPage()),
            'counts'      => $counts,
            'activeCount' => User::where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('users:manage');
        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:80'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/i', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'role'     => ['required', 'in:Administrator,Inventory Staff,Viewer'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user = User::create([
            'name'       => trim($data['name']),
            'username'   => strtolower(trim($data['username'])),
            'email'      => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'role'       => $data['role'],
            'password'   => $data['password'],
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        $this->logActivity($request, 'Created user account', 'User', (string) $user->id, $user->username, "{$user->name} assigned as {$user->role}");

        return response()->json(['message' => 'User account created.', 'user' => new UserResource($user)], 201);
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('users:manage');
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'min:2', 'max:80'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['sometimes', 'in:Administrator,Inventory Staff,Viewer'],
        ]);

        if ($user->id === $request->user()->id && isset($data['role']) && $data['role'] !== 'Administrator') {
            return response()->json(['message' => 'You cannot remove your own administrator access.'], 400);
        }

        if (isset($data['name'])) $user->name = trim($data['name']);
        if (array_key_exists('email', $data)) $user->email = ! empty($data['email']) ? strtolower(trim($data['email'])) : null;
        if (isset($data['role'])) $user->role = $data['role'];
        $user->save();

        $this->logActivity($request, 'Updated user account', 'User', (string) $user->id, $user->username, "Name: {$user->name}; Role: {$user->role}");

        return response()->json(['message' => 'User account updated.', 'user' => new UserResource($user)]);
    }

    public function toggle(Request $request, User $user)
    {
        Gate::authorize('users:manage');
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 400);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $action = $user->is_active ? 'Activated account' : 'Deactivated account';
        $this->logActivity($request, $action, 'User', (string) $user->id, $user->username);

        return response()->json([
            'message' => 'Account ' . ($user->is_active ? 'activated' : 'deactivated') . '.',
            'user'    => new UserResource($user),
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        Gate::authorize('users:manage');
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:72']]);

        $user->update(['password' => $data['password'], 'password_changed_at' => now()]);
        $this->logActivity($request, 'Reset user password', 'User', (string) $user->id, $user->username);

        return response()->json(['message' => "Password reset for {$user->name}."]);
    }
}