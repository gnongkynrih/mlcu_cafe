<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];
    
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function cafeTable(){
        return $this->belongsTo(CafeTable::class);
    }
}
