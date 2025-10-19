<?php

// app/Models/SalesLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sale_id',
        'action',
    ];

    // Relationship to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship to Sale
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
