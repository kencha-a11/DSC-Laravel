<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'status',
    ];

    // Many-to-many categories
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product', 'product_id', 'category_id');
    }


    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function images_path()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }
}

