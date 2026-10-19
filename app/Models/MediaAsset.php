<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'alt_text', 'caption', 'metadata', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
