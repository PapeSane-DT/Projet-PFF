<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Recolte;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use AfricasTalking\SDK\AfricasTalking;

class CommandeController extends Controller
{private function envoyerSMS($telephone, $message)
{
    try {
        $at  = new AfricasTalking(env('AT_USERNAME'), env('AT_API_KEY'));
        $sms = $at->sms();
        $sms->send([
            'to'      => '+221' . $telephone,
            'message' => $message,
        ]);
    } catch (\Exception $e) {
        // SMS échoue mais la commande continue
        \Log::error('SMS non envoyé: ' . $e->getMessage());
    }
}

    // Client passe une commande
    public function store(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role !== 'client') {
            return response()->json([
                'message' => 'Seuls les clients peuvent passer une commande'
            ], 403);
        }

        $request->validate([
            'recolte_id' => 'required|exists:recoltes,id',
        ]);

        $recolte = Recolte::with('agriculteur')->findOrFail($request->recolte_id);

        if ($recolte->statut !== 'disponible') {
            return response()->json([
                'message' => 'Cette récolte n\'est plus disponible'
            ], 400);
        }

        $montant = $recolte->quantite * $recolte->prix_unitaire;

        $commande = Commande::create([
            'recolte_id'    => $recolte->id,
            'client_id'     => $user->id,
            'montant_total' => $montant,
            'statut'        => 'en_attente',
        ]);

        $recolte->update(['statut' => 'vendu']);

        // SMS automatique à l'agriculteur
        $this->envoyerSMS(
            $recolte->agriculteur->telephone,
            "Bonjour " . $recolte->agriculteur->name . " ! " .
            $user->name . " a commandé votre récolte de " .
            $recolte->produit . ". Montant : " . number_format($montant) . " FCFA."
        );

        return response()->json([
            'message'  => 'Commande passée avec succès ! SMS envoyé à l\'agriculteur.',
            'commande' => $commande,
            'montant'  => number_format($montant) . ' FCFA',
        ], 201);
    }

    // Transporteur s'assigne une livraison
    public function assignTransport(Request $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role !== 'transporteur') {
            return response()->json([
                'message' => 'Seuls les transporteurs peuvent s\'assigner une livraison'
            ], 403);
        }

        $commande = Commande::with('client')->findOrFail($id);
        $commande->update([
            'transporteur_id' => $user->id,
            'statut'          => 'en_livraison',
        ]);

        // SMS automatique au client
        $this->envoyerSMS(
            $commande->client->telephone,
            "Bonjour " . $commande->client->name . " ! " .
            $user->name . " a pris en charge votre livraison. Elle est en route !"
        );

        return response()->json([
            'message'  => 'Livraison assignée avec succès ! SMS envoyé au client.',
            'commande' => $commande,
        ]);
    }
}