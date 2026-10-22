<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecolteController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\AdminController;


// Routes d'authentification
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');


// Routes protégées par JWT
Route::middleware('auth:api')->group(function () {
    // Authentification
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    // ... reste des routes
});

    // Récoltes
    // Récoltes
Route::post('/recoltes',        [RecolteController::class, 'store']);
Route::get('/recoltes',         [RecolteController::class, 'index']);
Route::get('/mes-recoltes',     [RecolteController::class, 'mesRecoltes']);
Route::put('/recoltes/{id}',    [RecolteController::class, 'update']);
Route::delete('/recoltes/{id}', [RecolteController::class, 'destroy']);

    // Commandes
    Route::post('/commandes',                      [CommandeController::class, 'store']);
    Route::get('/mes-commandes',                   [CommandeController::class, 'mesCommandes']);
    Route::post('/commandes/{id}/payer-wave',       [CommandeController::class, 'payerWave']);
    Route::put('/commandes/{id}/assign-transport', [CommandeController::class, 'assignTransport']);
    Route::get('/commandes-sur-mes-recoltes', [CommandeController::class, 'commandesSurMesRecoltes']);
    Route::put('/commandes/{id}/confirmer', [CommandeController::class, 'confirmerCommande']);
    Route::get('/commandes-disponibles', [CommandeController::class, 'commandesDisponibles']);
    Route::get('/mes-livraisons',              [CommandeController::class, 'mesLivraisons']);
    Route::put('/commandes/{id}/livre',        [CommandeController::class, 'marquerLivre']);
    Route::delete('/commandes/{id}', [CommandeController::class, 'annulerCommande']);

    // Profil et statistiques
Route::get('/agriculteur/{id}',  [ProfilController::class, 'voirAgriculteur']);
Route::put('/mon-profil',        [ProfilController::class, 'modifierProfil']);
Route::get('/statistiques',      [ProfilController::class, 'statistiques']);
Route::get('/client/{id}', [ProfilController::class, 'voirClient']);

// Routes Admin
Route::middleware('auth:api')->group(function () {
    Route::get('/admin/dashboard',              [AdminController::class, 'dashboard']);
    Route::get('/admin/utilisateurs',           [AdminController::class, 'utilisateurs']);
    Route::get('/admin/recoltes',               [AdminController::class, 'recoltes']);
    Route::get('/admin/commandes',              [AdminController::class, 'commandes']);
    Route::put('/admin/utilisateurs/{id}/toggle', [AdminController::class, 'toggleSuspendre']);
    Route::delete('/admin/utilisateurs/{id}',  [AdminController::class, 'supprimerUtilisateur']);
    Route::post('/admin/creer-admin',           [AdminController::class, 'creerAdmin']);
});
});