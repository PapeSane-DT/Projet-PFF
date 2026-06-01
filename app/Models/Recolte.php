<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recolte extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'produit',
        'quantite',
        'prix_unitaire',
        'zone',
        'date_recolte',
        'statut',
    ];

    // Une récolte appartient à un agriculteur
    public function agriculteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}