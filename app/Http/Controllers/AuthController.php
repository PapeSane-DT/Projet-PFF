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
    'name'      => 'required|string|max:100',
    'telephone' => 'required|unique:users|regex:/^[0-9]{8,10}$/',
    'password'  => 'required|min:6',
    'role'      => 'required|in:agriculteur,transporteur,client',
], [
    'telephone.regex'  => 'Le téléphone doit contenir exactement 9 chiffres.',
    'password.regex'   => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
    'password.min'     => 'Le mot de passe doit contenir au moins 8 caractères.',
    'telephone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
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

    $user = JWTAuth::user();

    if ($user->suspendu) {
        return response()->json([
            'message' => 'Votre compte est suspendu. Contactez l\'administrateur.'
        ], 403);
    }

    $user = JWTAuth::user();

    return response()->json([
        'message' => 'Connexion réussie !',
        'token'   => $token,
        'user'    => $user,
    ]);
}

    // Déconnexion
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Déconnexion réussie !']);
    }
    // Récupérer l'utilisateur connecté
public function me()
{
    $user = JWTAuth::parseToken()->authenticate();
    return response()->json(['user' => $user]);
}
}