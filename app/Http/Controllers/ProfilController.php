<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Recolte;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    // Voir le profil d'un agriculteur
    public function voirAgriculteur($id)
    {
        $agriculteur = User::where('id', $id)
            ->where('role', 'agriculteur')
            ->firstOrFail();

        $recoltes = Recolte::where('user_id', $id)->get();
        $vendues = $recoltes->where('statut', 'vendu')->count();
        $disponibles = $recoltes->where('statut', 'disponible')->count();

        return response()->json([
            'agriculteur' => [
                'id'          => $agriculteur->id,
                'name'        => $agriculteur->name,
                'telephone'   => $agriculteur->telephone,
                'membre_depuis' => $agriculteur->created_at->format('d/m/Y'),
            ],
            'statistiques' => [
                'total_recoltes'   => $recoltes->count(),
                'recoltes_vendues' => $vendues,
                'recoltes_disponibles' => $disponibles,
            ]
        ]);
    }

    // Modifier son profil
    public function modifierProfil(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'name'     => 'sometimes|string',
            'password' => 'sometimes|min:6',
        ]);

        if ($request->name) {
            $user->name = $request->name;
        }

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Profil modifié avec succès !',
            'user'    => $user,
        ]);
    }

    // Tableau de bord statistiques
    public function statistiques()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role === 'agriculteur') {
            $recoltes = Recolte::where('user_id', $user->id)->get();
            $vendues = $recoltes->where('statut', 'vendu')->count();
            $revenus = \App\Models\Commande::whereHas('recolte', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('statut', '!=', 'en_attente')->sum('montant_total');

            return response()->json([
                'total_recoltes'       => $recoltes->count(),
                'recoltes_vendues'     => $vendues,
                'recoltes_disponibles' => $recoltes->where('statut', 'disponible')->count(),
                'revenus_total'        => $revenus,
            ]);
        }

        if ($user->role === 'client') {
            $commandes = \App\Models\Commande::where('client_id', $user->id)->get();
            $total_depense = $commandes->sum('montant_total');

            return response()->json([
                'total_commandes'   => $commandes->count(),
                'commandes_livrees' => $commandes->whereIn('statut', ['livré', 'livre'])->count(),
                'total_depense'     => $total_depense,
            ]);
        }

        if ($user->role === 'transporteur') {
            $livraisons = \App\Models\Commande::where('transporteur_id', $user->id)->get();

            return response()->json([
                'total_livraisons'    => $livraisons->count(),
                'livraisons_terminees' => $livraisons->whereIn('statut', ['livré', 'livre'])->count(),
                'en_cours'            => $livraisons->where('statut', 'en_livraison')->count(),
            ]);
        }
    }
    // Voir le profil d'un client
public function voirClient($id)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'agriculteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $client = User::where('id', $id)
        ->where('role', 'client')
        ->firstOrFail();

    $commandes = \App\Models\Commande::where('client_id', $id)->get();

    return response()->json([
        'client' => [
            'id'            => $client->id,
            'name'          => $client->name,
            'telephone'     => $client->telephone,
            'membre_depuis' => $client->created_at->format('d/m/Y'),
        ],
        'statistiques' => [
            'total_commandes'   => $commandes->count(),
            'commandes_livrees' => $commandes->whereIn('statut', ['livré', 'livre'])->count(),
        ]
    ]);
}
}