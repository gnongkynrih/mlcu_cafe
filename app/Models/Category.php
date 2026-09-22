<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    //since we are using the soft deletes, we need to tell the model to use the soft deletes
    use SoftDeletes, HasFactory;

    //allow mass assignment to all fields except id
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ];
    }

    //relationship with menu items
    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }
}
