<?php

namespace App\Http\Controllers;

use App\Models\Recolte;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class RecolteController extends Controller
{
    // Publier une récolte (agriculteur seulement)
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role !== 'agriculteur') {
            return response()->json([
                'message' => 'Seuls les agriculteurs peuvent publier une récolte'
            ], 403);
        }

        $request->validate([
            'produit'       => 'required|string',
            'quantite'      => 'required|numeric',
            'prix_unitaire' => 'required|numeric',
            'zone'          => 'required|string',
            'date_recolte'  => 'required|date',
        ]);

        $recolte = Recolte::create([
            'user_id'       => $user->id,
            'produit'       => $request->produit,
            'quantite'      => $request->quantite,
            'prix_unitaire' => $request->prix_unitaire,
            'zone'          => $request->zone,
            'date_recolte'  => $request->date_recolte,
        ]);

        return response()->json([
            'message' => 'Récolte publiée avec succès !',
            'recolte' => $recolte,
        ], 201);
    }

    // Voir toutes les récoltes
    public function index(Request $request)
    {
        $recoltes = Recolte::with('agriculteur')
            ->when($request->zone, function ($query) use ($request) {
                $query->where('zone', $request->zone);
            })
            ->where('statut', 'disponible')
            ->get();

        return response()->json($recoltes);
    }
}