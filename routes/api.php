<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecolteController;
use App\Http\Controllers\CommandeController;

// Routes d'authentification
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// Routes protégées par JWT
Route::middleware('auth:api')->group(function () {
    // Authentification
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Récoltes
    Route::post('/recoltes', [RecolteController::class, 'store']);
    Route::get('/recoltes',  [RecolteController::class, 'index']);

    // Commandes
    Route::post('/commandes',                        [CommandeController::class, 'store']);
    Route::put('/commandes/{id}/assign-transport',   [CommandeController::class, 'assignTransport']);
});