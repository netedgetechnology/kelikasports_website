<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    public const TYPES = [
        'hero', 'split', 'rich_text', 'card_grid', 'sport_directory', 'program_list', 'facilities_showcase',
        'feature_list', 'five_elements', 'stats', 'team', 'event_list', 'gallery', 'testimonials', 'faq',
        'cta', 'contact_details', 'contact_form', 'map_embed', 'divider',
    ];

    protected $fillable = ['page_id', 'type', 'admin_label', 'sort_order', 'is_visible', 'data'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'data' => 'array'];
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
