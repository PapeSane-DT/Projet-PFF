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

    if ($user->suspendu) {
        return response()->json([
            'message' => 'Votre compte est suspendu. Contactez l\'administrateur.'
        ], 403);
    }

    $request->validate([
        'produit'       => 'required|string',
        'quantite'      => 'required|numeric',
        'prix_unitaire' => 'required|numeric',
        'zone'          => 'required|string',
        'date_recolte'  => 'required|date',
        'image'         => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('recoltes', 'public');
    }

    $recolte = Recolte::create([
        'user_id'       => $user->id,
        'produit'       => $request->produit,
        'quantite'      => $request->quantite,
        'prix_unitaire' => $request->prix_unitaire,
        'zone'          => $request->zone,
        'date_recolte'  => $request->date_recolte,
        'image'         => $imagePath,
    ]);

    return response()->json([
        'message' => 'Récolte publiée avec succès !',
        'recolte' => $recolte,
    ], 201);
}

    // Voir toutes les récoltes disponibles
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

    // Voir ses propres récoltes (agriculteur)
    public function mesRecoltes()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role !== 'agriculteur') {
            return response()->json([
                'message' => 'Accès refusé'
            ], 403);
        }

        $recoltes = Recolte::where('user_id', $user->id)->get();

        return response()->json($recoltes);
    }

    // Modifier une récolte
   public function update(Request $request, $id)
{
    $user = JWTAuth::parseToken()->authenticate();
    $recolte = Recolte::findOrFail($id);

    if ($recolte->user_id !== $user->id) {
        return response()->json([
            'message' => 'Vous ne pouvez pas modifier cette récolte'
        ], 403);
    }

    $request->validate([
        'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    if ($request->hasFile('image')) {
        // Supprimer l'ancienne image
        if ($recolte->image) {
            \Storage::disk('public')->delete($recolte->image);
        }
        $recolte->image = $request->file('image')->store('recoltes', 'public');
    }

    $recolte->update($request->only([
        'produit', 'quantite', 'prix_unitaire', 'zone', 'date_recolte'
    ]));

    if ($request->hasFile('image')) {
        $recolte->save();
    }

    return response()->json([
        'message' => 'Récolte modifiée avec succès !',
        'recolte' => $recolte,
    ]);
}

    // Supprimer une récolte
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $recolte = Recolte::findOrFail($id);

        if ($recolte->user_id !== $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer cette récolte'
            ], 403);
        }

        $recolte->delete();

        return response()->json([
            'message' => 'Récolte supprimée avec succès !'
        ]);
    }
}