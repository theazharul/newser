<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['media_id', 'variant_name', 'file_path', 'width', 'height', 'size'])]
class MediaVariant extends Model
{
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
