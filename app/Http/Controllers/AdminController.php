<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Recolte;
use App\Models\Commande;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    private function verifierAdmin()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->role !== 'admin') {
            abort(403, 'Accès refusé');
        }
        return $user;
    }

    // Tableau de bord global
    public function dashboard()
    {
        $this->verifierAdmin();

        return response()->json([
            'utilisateurs' => [
                'total'         => User::count(),
                'agriculteurs'  => User::where('role', 'agriculteur')->count(),
                'clients'       => User::where('role', 'client')->count(),
                'transporteurs' => User::where('role', 'transporteur')->count(),
            ],
            'recoltes' => [
                'total'       => Recolte::count(),
                'disponibles' => Recolte::where('statut', 'disponible')->count(),
                'vendues'     => Recolte::where('statut', 'vendu')->count(),
            ],
            'commandes' => [
                'total'       => Commande::count(),
                'en_attente'  => Commande::where('statut', 'en_attente')->count(),
                'en_livraison'=> Commande::where('statut', 'en_livraison')->count(),
                'livrees'     => Commande::whereIn('statut', ['livré', 'livre'])->count(),
            ],
            'revenus_total' => Commande::where('statut', '!=', 'en_attente')->sum('montant_total'),
        ]);
    }

    // Liste tous les utilisateurs
    public function utilisateurs()
    {
        $this->verifierAdmin();
        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    // Liste toutes les récoltes
    public function recoltes()
    {
        $this->verifierAdmin();
        $recoltes = Recolte::with('agriculteur')->orderBy('created_at', 'desc')->get();
        return response()->json($recoltes);
    }

    // Liste toutes les commandes
    public function commandes()
    {
        $this->verifierAdmin();
        $commandes = Commande::with(['recolte', 'client', 'transporteur'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($commandes);
    }

    // Suspendre / Réactiver un utilisateur
    public function toggleSuspendre($id)
    {
        $this->verifierAdmin();
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Impossible de suspendre un admin'], 403);
        }

        $user->suspendu = !$user->suspendu;
        $user->save();

        return response()->json([
            'message' => $user->suspendu ? 'Utilisateur suspendu !' : 'Utilisateur réactivé !',
            'user'    => $user,
        ]);
    }

    // Supprimer un utilisateur
    public function supprimerUtilisateur($id)
    {
        $this->verifierAdmin();
        $user = User::findOrFail($id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Impossible de supprimer un admin'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé !']);
    }

    // Créer un compte admin
    public function creerAdmin(Request $request)
    {
        $this->verifierAdmin();
        $request->validate([
            'name'      => 'required|string',
            'telephone' => 'required|unique:users',
            'password'  => 'required|min:6',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'role'      => 'admin',
        ]);

        return response()->json([
            'message' => 'Admin créé avec succès !',
            'user'    => $user,
        ], 201);
    }
}