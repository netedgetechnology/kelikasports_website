<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends Model
{
    use SoftDeletes;

    public const TYPES = ['sport', 'program', 'facility', 'event', 'coach', 'faq', 'gallery', 'testimonial'];

    protected $fillable = [
        'type', 'title', 'slug', 'status', 'sort_order', 'data', 'seo_title', 'seo_description',
        'starts_at', 'ends_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
