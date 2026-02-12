<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFaceEmbedding extends Model
{
    protected $fillable = [
        'user_id',
        'embedding',
        'image_path',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'is_primary' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEmbeddingVector(): array
    {
        return $this->embedding ?? [];
    }

    public function setEmbeddingVector(array $vector): void
    {
        $this->embedding = $vector;
    }
}