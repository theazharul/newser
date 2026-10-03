<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'file_name',
    'file_path',
    'mime_type',
    'size',
    'width',
    'height',
    'alt_text',
    'caption',
    'credit',
])]
class Media extends Model
{
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }
}
