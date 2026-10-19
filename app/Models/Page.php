<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'template', 'status', 'show_in_menu', 'sort_order', 'seo_title', 'seo_description',
        'canonical_url', 'robots_directive', 'og_title', 'og_description', 'og_media_id', 'created_by',
        'updated_by', 'published_at',
    ];

    protected function casts(): array
    {
        return ['show_in_menu' => 'boolean', 'published_at' => 'datetime'];
    }

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    public function visibleSections()
    {
        return $this->sections()->where('is_visible', true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ogMedia()
    {
        return $this->belongsTo(MediaAsset::class, 'og_media_id');
    }

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
