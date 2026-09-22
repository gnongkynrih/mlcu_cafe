<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends Model
{
    use SoftDeletes, HasFactory;

    protected $guarded = ['id'];
    
    //relationship with category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

