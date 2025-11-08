<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SalesLog;
use Illuminate\Support\Facades\Auth;

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

    // for sale must to exist user required
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // in order to have sale product items list is available
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    
    public function salesLogs()
    {
        return $this->hasMany(SalesLog::class);
    }


    // // query get sale items with product
    // public function saleItemsWithProduct()
    // {
    //     return $this->hasMany(SaleItem::class)->with('product');
    // }
    
}
