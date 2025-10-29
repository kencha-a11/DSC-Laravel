<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'stock_quantity',
        'low_stock_threshold',
        'status',
        'image_path',
    ];

    // many products have many categories
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product', 'product_id', 'category_id');
    }

    // list of product items in sales
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    protected static function booted()
    {
        static::deleting(function ($product) {
            // Detach categories from pivot table
            $product->categories()->detach();

            // Do NOT delete sale items; product_id will be null automatically
        });
    }



    // public function images_path()
    // {
    //     return $this->hasMany(ProductImage::class, 'product_id', 'id');
    // }

    // public function primaryImage()
    // {
    //     return $this->hasOne(ProductImage::class)->where('is_primary', true);
    // }

    // public function getRawImagePathAttribute()
    // {
    //     // remove the 'storage/' prefix if present
    //     return str_replace('storage/', '', str_replace(asset('storage/') . '/', '', $this->attributes['image_path']));
    // }
}
