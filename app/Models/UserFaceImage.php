<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserFaceImage extends Model
{
    protected $fillable = [
        'user_id',
        'image_path',
        'is_primary',
        'order',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = [
        'image_url',
        'formatted_metadata',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path 
            ? Storage::disk('public')->url($this->image_path)
            : '';
    }

    public function getFormattedMetadataAttribute(): array
    {
        return array_merge($this->metadata ?? [], [
            'uploaded_at_formatted' => isset($this->metadata['uploaded_at']) 
                ? \Carbon\Carbon::parse($this->metadata['uploaded_at'])->format('F j, Y H:i:s')
                : null,
            'confidence_percentage' => isset($this->metadata['confidence_score'])
                ? round($this->metadata['confidence_score'] * 100) . '%'
                : null,
        ]);
    }
}