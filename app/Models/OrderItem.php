<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    // protected $guarded = ['id'];
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name',
        'unit_price',
        'quantity',
        'subtotal',
        'note',
        'status',
        'cancel_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
