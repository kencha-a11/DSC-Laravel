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

    // ✅ Relationship to Sale (needed!)
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Relationship to Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getSubtotalAttribute($value)
    {
        return $this->snapshot_price * $this->snapshot_quantity;
    }

    public function getNameAttribute()
    {
        return $this->product?->name ?? $this->snapshot_name ?? 'Deleted Product';
    }

    public function getPriceAttribute($value)
    {
        return $this->product?->price ?? $this->snapshot_price;
    }

    public function getQuantityAttribute($value)
    {
        return $this->product ? $value : $this->snapshot_quantity;
    }
}
