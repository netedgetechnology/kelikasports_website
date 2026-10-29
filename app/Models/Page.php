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

    /** Page settings captured in every revision. Restoring a revision only ever touches the keys it contains. */
    public const REVISION_FIELDS = ['title', 'slug', 'template', 'status', 'published_at', 'seo_title', 'seo_description', 'canonical_url', 'robots_directive'];

    public const DRAFT = 'draft';
    public const SCHEDULED = 'scheduled';
    public const PUBLISHED = 'published';
    public const STATUSES = [self::DRAFT, self::SCHEDULED, self::PUBLISHED];

    /**
     * Publication rules, used everywhere a page's visibility matters:
     * - draft: never public;
     * - scheduled: public from published_at onwards (kelika:publish-scheduled then records it as published);
     * - published: public from published_at onwards (always in the past, except older records saved with a future date);
     * - archived (soft-deleted): never public, whatever its status.
     * Visibility depends only on the clock, never on the scheduler having run, so a page can be neither early nor late.
     */
    public const LIVE_STATUSES = [self::SCHEDULED, self::PUBLISHED];

    /** Pages that must always stay live once they are: they can be neither archived, unpublished nor rescheduled. */
    public const CORE_SLUGS = ['home', 'contact'];

    protected function casts(): array
    {
        return ['show_in_menu' => 'boolean', 'published_at' => 'datetime'];
    }

    /** The page settings and all its sections, in the form stored in page_revisions.snapshot. */
    public function revisionSnapshot(): array
    {
        return [
            // The date is stored as local wall-clock time, never as a UTC string that would read back hours apart.
            'page' => ['published_at' => $this->published_at?->format('Y-m-d H:i:s')] + $this->only(self::REVISION_FIELDS),
            'sections' => $this->sections()->orderBy('sort_order')->get()->toArray(),
        ];
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

    /** Pages visitors can see now. Compares with PHP's clock (Asia/Kolkata), never the database's own NOW(). */
    public function scopePublished($query)
    {
        return $query->whereIn('status', self::LIVE_STATUSES)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Pages set to go live later. */
    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', self::LIVE_STATUSES)->where('published_at', '>', now());
    }

    /** Scheduled pages whose time has come, waiting for kelika:publish-scheduled to record them as published. */
    public function scopeDueForPublication($query)
    {
        return $query->where('status', self::SCHEDULED)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** "archived", "draft", "scheduled" (live later) or "published" (live now), by the rules above. */
    public function publicationState(): string
    {
        if ($this->trashed()) return 'archived';
        if (! in_array($this->status, self::LIVE_STATUSES, true) || ! $this->published_at) return self::DRAFT;

        return $this->published_at->isFuture() ? self::SCHEDULED : self::PUBLISHED;
    }

    public function isLive(): bool
    {
        return $this->publicationState() === self::PUBLISHED;
    }

    /** Live or set to go live: its Hero image must stay in place, as it is (or will be) shown to visitors. */
    public function isPublishedOrScheduled(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }

    public function isCorePage(): bool
    {
        return in_array($this->slug, self::CORE_SLUGS, true);
    }

    /** For the CMS, e.g. "Published", "Scheduled · 01 Nov 2026, 09:30", "Draft" or "Archived". */
    public function publicationLabel(): string
    {
        $state = $this->publicationState();

        return $state === self::SCHEDULED ? 'Scheduled · '.$this->published_at->format('d M Y, H:i') : ucfirst($state);
    }
}
