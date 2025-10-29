<?php

// app/Models/InventoryLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'action',
        'quantity_change',
    ];

    // all users have inventory logs
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // this inventory log consist of product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
