<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    use HasFactory;

    protected $fillable = [
    'recolte_id',
    'client_id',
    'transporteur_id',
    'quantite',
    'montant_total',
    'statut',
];

    // Une commande appartient à une récolte
    public function recolte()
    {
        return $this->belongsTo(Recolte::class);
    }

    // Une commande appartient à un client
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    // Une commande appartient à un transporteur
    public function transporteur()
    {
        return $this->belongsTo(User::class, 'transporteur_id');
    }
}