<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Recolte;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use AfricasTalking\SDK\AfricasTalking;

class CommandeController extends Controller
{
    const WAVE_MERCHANT_URL = 'https://pay.wave.com/m/M_sn_knnmQCZiVi3h/c/sn/';

    // Envoyer un SMS
    private function envoyerSMS($telephone, $message)
    {
        try {
            $at  = new AfricasTalking(env('AT_USERNAME'), env('AT_API_KEY'));
            $sms = $at->sms();
            $sms->send([
                'to'      => '+221' . $telephone,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            \Log::error('SMS non envoyé: ' . $e->getMessage());
        }
    }
public function store(Request $request)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'client') {
        return response()->json([
            'message' => 'Seuls les clients peuvent passer une commande'
        ], 403);
    }
    if ($user->suspendu) {
    return response()->json([
        'message' => 'Votre compte est suspendu. Contactez l\'administrateur.'
    ], 403);
}

    $request->validate([
        'recolte_id' => 'required|exists:recoltes,id',
        'quantite'   => 'required|numeric|min:1',
    ]);

    $recolte = Recolte::with('agriculteur')->findOrFail($request->recolte_id);

    if ($recolte->statut !== 'disponible') {
        return response()->json([
            'message' => 'Cette récolte n\'est plus disponible'
        ], 400);
    }

    if ($request->quantite > $recolte->quantite) {
        return response()->json([
            'message' => 'Quantité demandée dépasse le stock disponible (' . $recolte->quantite . ' kg)'
        ], 400);
    }

    $montant = $request->quantite * $recolte->prix_unitaire;

    $estPayeWave = ($request->mode_paiement === 'wave');
    $statutInitial = $estPayeWave ? 'payé' : 'en_attente';

    $commande = Commande::create([
        'recolte_id'    => $recolte->id,
        'client_id'     => $user->id,
        'quantite'      => $request->quantite,
        'montant_total' => $montant,
        'statut'        => $statutInitial,
    ]);

    // Diminuer la quantité restante
    $nouvelleQuantite = $recolte->quantite - $request->quantite;

    if ($nouvelleQuantite <= 0) {
        $recolte->update([
            'quantite' => 0,
            'statut'   => $estPayeWave ? 'vendu' : 'en_attente_confirmation'
        ]);
    } else {
        $recolte->update(['quantite' => $nouvelleQuantite]);
    }

    $refWave = $estPayeWave ? ('WAVE-' . strtoupper(substr(md5(uniqid()), 0, 8))) : null;
    $waveUrl = $estPayeWave ? (self::WAVE_MERCHANT_URL . '?amount=' . intval($montant)) : null;

    $msgAgri = "Bonjour " . $recolte->agriculteur->name . " ! " .
        $user->name . " a " . ($estPayeWave ? "commandé et PAYÉ PAR WAVE" : "commandé") . " " .
        $request->quantite . " kg de " . $recolte->produit .
        ". Montant : " . number_format($montant) . " FCFA." .
        ($refWave ? " (Réf Wave: " . $refWave . ")" : "");

    $this->envoyerSMS($recolte->agriculteur->telephone, $msgAgri);

    return response()->json([
        'message'          => $estPayeWave ? 'Commande et paiement Wave réussis !' : 'Commande passée avec succès !',
        'commande'         => $commande,
        'montant'          => number_format($montant) . ' FCFA',
        'quantite_restante' => $nouvelleQuantite . ' kg restants',
        'reference_wave'   => $refWave,
        'wave_url'         => $waveUrl,
    ], 201);
}

    // Voir ses propres commandes (client)
    public function mesCommandes()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->role !== 'client') {
            return response()->json([
                'message' => 'Accès refusé'
            ], 403);
        }

        $commandes = Commande::with('recolte')
            ->where('client_id', $user->id)
            ->get();

        return response()->json($commandes);
    }
    // Agriculteur voit les commandes sur ses récoltes
public function commandesSurMesRecoltes()
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'agriculteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commandes = Commande::with(['recolte', 'client', 'transporteur'])
        ->whereHas('recolte', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->get();

    return response()->json($commandes);
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

        $this->envoyerSMS(
            $commande->client->telephone,
            "Bonjour " . $commande->client->name . " ! " .
            $user->name . " a pris en charge votre livraison. Elle est en route !"
        );

        return response()->json([
            'message'  => 'Livraison assignée avec succès !',
            'commande' => $commande,
        ]);
        
    }
    // Agriculteur confirme une commande
public function confirmerCommande($id)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'agriculteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commande = Commande::with(['client', 'recolte'])->findOrFail($id);
    
    $commande->update(['statut' => 'payé']);
    
    $commande->recolte->update(['statut' => 'vendu']);

    $this->envoyerSMS(
        $commande->client->telephone,
        "Bonjour " . $commande->client->name . " ! Votre commande de " . 
        $commande->recolte->produit . " a été confirmée. Un transporteur va bientôt prendre en charge la livraison."
    );

    return response()->json([
        'message'  => 'Commande confirmée avec succès !',
        'commande' => $commande,
    ]);
}

// Client effectue le paiement Wave pour sa commande
public function payerWave(Request $request, $id)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'client') {
        return response()->json([
            'message' => 'Seuls les clients peuvent effectuer un paiement'
        ], 403);
    }

    $commande = Commande::with(['client', 'recolte.agriculteur'])->findOrFail($id);

    if ($commande->client_id !== $user->id) {
        return response()->json([
            'message' => 'Vous ne pouvez payer que vos propres commandes'
        ], 403);
    }

    if ($commande->statut === 'payé' || $commande->statut === 'en_livraison' || $commande->statut === 'livré') {
        return response()->json([
            'message' => 'Cette commande a déjà été réglée'
        ], 400);
    }

    $commande->update(['statut' => 'payé']);

    if ($commande->recolte && $commande->recolte->quantite <= 0) {
        $commande->recolte->update(['statut' => 'vendu']);
    }

    $refWave = 'WAVE-' . strtoupper(substr(md5(uniqid()), 0, 8));

    if ($commande->recolte && $commande->recolte->agriculteur) {
        $this->envoyerSMS(
            $commande->recolte->agriculteur->telephone,
            "Paiement Wave reçu ! " . $user->name . " a payé " . number_format($commande->montant_total) . " FCFA via Wave pour votre récolte de " . $commande->recolte->produit . " (Réf: " . $refWave . ")."
        );
    }

    $this->envoyerSMS(
        $user->telephone,
        "Paiement Wave validé ! Votre commande #" . $commande->id . " de " . number_format($commande->montant_total) . " FCFA a été payée avec succès. Réf: " . $refWave
    );

    $waveUrl = self::WAVE_MERCHANT_URL . '?amount=' . intval($commande->montant_total);

    return response()->json([
        'message'   => 'Paiement Wave validé avec succès !',
        'commande'  => $commande,
        'reference' => $refWave,
        'wave_url'  => $waveUrl,
    ]);
}

// Voir toutes les commandes disponibles pour livraison
public function commandesDisponibles()
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'transporteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commandes = Commande::with(['recolte', 'client'])
        ->where('statut', 'payé')
        ->whereNull('transporteur_id')
        ->get();

    return response()->json($commandes);
}
// Transporteur voit ses livraisons
public function mesLivraisons()
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'transporteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commandes = Commande::with(['recolte', 'client'])
        ->where('transporteur_id', $user->id)
        ->get();

    return response()->json($commandes);
}

// Transporteur marque une commande comme livrée
public function marquerLivre($id)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'transporteur') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commande = Commande::with('client')->findOrFail($id);
    $commande->update(['statut' => 'livré']);

    $this->envoyerSMS(
        $commande->client->telephone,
        "Bonjour " . $commande->client->name . " ! Votre commande a été livrée avec succès. Merci d'utiliser SunuRécolte !"
    );

    return response()->json([
        'message'  => 'Commande marquée comme livrée !',
        'commande' => $commande,
    ]);
}
// Client annule une commande
public function annulerCommande($id)
{
    $user = JWTAuth::parseToken()->authenticate();

    if ($user->role !== 'client') {
        return response()->json([
            'message' => 'Accès refusé'
        ], 403);
    }

    $commande = Commande::with('recolte')->findOrFail($id);

    if ($commande->client_id !== $user->id) {
        return response()->json([
            'message' => 'Vous ne pouvez pas annuler cette commande'
        ], 403);
    }

    if ($commande->statut !== 'en_attente') {
        return response()->json([
            'message' => 'Impossible d\'annuler — commande déjà confirmée ou en livraison'
        ], 400);
    }

    // Remettre le stock
    $commande->recolte->update([
        'quantite' => $commande->recolte->quantite + $commande->quantite,
        'statut'   => 'disponible'
    ]);

    $commande->delete();

    return response()->json([
        'message' => 'Commande annulée avec succès !'
    ]);
}
}