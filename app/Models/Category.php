<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    //since we are using the soft deletes, we need to tell the model to use the soft deletes
    use SoftDeletes, HasFactory;
}
