<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function saleItems()
    {
        return $this->hasMany(\App\Models\SaleItem::class);
    }

    public function saleItemsWithProduct()
{
    return $this->hasMany(SaleItem::class)->with('product');
}

}
