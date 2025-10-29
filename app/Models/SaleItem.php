<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
        // All snapshot fields are explicitly made fillable
        'snapshot_name', 
        'snapshot_quantity',
        'snapshot_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'snapshot_quantity' => 'integer',
        'snapshot_price' => 'decimal:2',
    ];

    // Relationship to Sale
    // public function sale()
    // {
    //     return $this->belongsTo(Sale::class);
    // }

    // saleitem must have product to exist
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    

    // Always use snapshot subtotal
    public function getSubtotalAttribute($value)
    {
        return $this->snapshot_price * $this->snapshot_quantity;
    }
}