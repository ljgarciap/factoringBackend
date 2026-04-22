<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => $request->roles,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'roles' => 'required|array',
            'roles.*' => 'string|in:superadmin,gerente,operativo,cliente'
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'roles' => $request->roles,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        // Prevent deleting the last superadmin or yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo.'], 422);
        }

        $user->delete();
        return response()->json(null, 204);
    }
}
