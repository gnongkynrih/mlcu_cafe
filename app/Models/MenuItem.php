<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    // relationship with category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Public URL of the uploaded image, used in Blade as $menu->image_url.
     * Returns null when the item has no image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image ? Storage::disk('public')->url($this->image) : null);
    }
}
