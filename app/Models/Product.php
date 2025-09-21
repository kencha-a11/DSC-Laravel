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
        'category_id',
        'stock_quantity',
        'low_stock_threshold',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sale_items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // public function images()
    // {
    //     return $this->hasMany(\App\Models\ProductImage::class, 'product_id', 'id');
    // }

    public function images_path()
    {
        return $this->hasMany(\App\Models\ProductImage::class, 'product_id', 'id');
    }


}
