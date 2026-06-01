<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Inscription
    public function register(Request $request)
    {
        $request->validate([
            'name'      => 'required|string',
            'telephone' => 'required|unique:users',
            'password'  => 'required|min:6',
            'role'      => 'required|in:agriculteur,transporteur,client',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Inscription réussie !',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    // Connexion
    public function login(Request $request)
    {
        $credentials = $request->only('telephone', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'message' => 'Téléphone ou mot de passe incorrect'
            ], 401);
        }

        return response()->json([
            'message' => 'Connexion réussie !',
            'token'   => $token,
        ]);
    }

    // Déconnexion
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Déconnexion réussie !']);
    }
}