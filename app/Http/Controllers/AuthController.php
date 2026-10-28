<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    // Register user (stores hashed password)
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'user_id'  => 'required|string|max:255|unique:users,user_id',
            'password' => 'required|string|min:6|confirmed', // expects password_confirmation
            'password2' => 'required|string|min:6|confirmed', // second password, expects password2_confirmation
            'role'     => 'nullable|string',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'user_id'    => $request->user_id,
            'email'      => $request->email ?? null,
            'password'   => Hash::make($request->password),
            'password2'  => Hash::make($request->password2),
            'role'       => $request->role ?? 'User',
            'ip_address' => $request->ip(),
        ]);

        // hide password in response
        $user->makeHidden(['password']);

        return response()->json([
            'message' => 'User registered successfully',
            'user'    => $user
        ], 201);
    }

    // Login by user_id + password
    public function login(Request $request)
{
    $request->validate([
        'user_id'  => 'required|string',
        'password' => 'required|string',
        'password2' => 'nullable|string',
    ]);

    $user = User::where('user_id', $request->user_id)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'user_id' => ['The provided credentials are incorrect.']
        ]);
    }

    // Users with a second password must also enter it correctly.
    // Users without one (not set yet) log in with the first password only.
    if (!empty($user->password2) && !Hash::check((string) $request->password2, $user->password2)) {
        throw ValidationException::withMessages([
            'password2' => ['The second password is incorrect.']
        ]);
    }

    // Update IP or login info
    $user->update(['ip_address' => $request->ip()]);

    // Create Sanctum Token
    $token = $user->createToken('auth_token')->plainTextToken;

    // Hide password
    $user->makeHidden(['password']);

    return response()->json([
        'message' => 'Login successful',
        'token'   => $token,
        'user'    => $user
    ]);
}

}
