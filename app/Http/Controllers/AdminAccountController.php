<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['accounts' => User::orderBy('id')->get(['id', 'name', 'email', 'role'])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        return $this->save($request, new User);
    }

    public function update(Request $request, User $user)
    {
        return $this->save($request, $user);
    }

    private function save(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_ADMIN, User::ROLE_HOSPITAL])],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
        ]);
        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_ADMIN) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role' => __('You cannot remove your own admin role.'),
            ]);
        }
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        if (!empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return response()->json(['account' => $user->only(['id', 'name', 'email', 'role'])]);
    }
}
