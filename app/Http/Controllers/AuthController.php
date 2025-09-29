<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Registro de usuario (cliente)
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|unique:users,email',
            'password'              => 'required|string|confirmed|min:6',
        ]);

        $user = User::create([
            'name'      => $fields['name'],
            'email'     => strtolower($fields['email']),
            'password'  => bcrypt($fields['password']),
            'role'      => 'cliente', // explícito, aunque tu ENUM tenga default
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return ApiResponse::success('Usuario registrado correctamente.', [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'role'         => $user->role,   // <-- ENUM, no Spatie
            'access_token' => $token,
        ], 201);
    }

    // Login de usuario (cliente)
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower($fields['email']))->first();

        if (! $user || ! Hash::check($fields['password'], $user->password)) {
            return ApiResponse::error('Usuario no autorizado', [], 401);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return ApiResponse::success('Usuario logueado exitosamente!', [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'role'         => $user->role,   // <-- aquí estaba el detalle
            'access_token' => $token,
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Sesión cerrada correctamente.', [], 200);
    }
}
