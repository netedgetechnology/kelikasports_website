<?php

namespace App\Models;

use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Enquiry extends Model
{
    use SoftDeletes;

    /** Every status an enquiry can have, in the order the CMS lists them. */
    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'spam' => 'Spam'];

    public const SPAM = 'spam';

    protected $fillable = [
        'name', 'email', 'phone', 'category', 'message', 'status', 'internal_notes',
        'source_page', 'ip_hash', 'consented_at',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    /**
     * The CMS list and CSV export share these filters: a search of name, email and message (typed % and _ match
     * themselves), a status (spam is left out unless asked for), and a received-date range in whole local days.
     *
     * @param  array{q?: ?string, status?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        if (($term = trim((string) ($filters['q'] ?? ''))) !== '') Like::anyColumn($query, ['name', 'email', 'message'], $term);

        empty($filters['status']) ? $query->where('status', '!=', self::SPAM) : $query->where('status', $filters['status']);

        if (! empty($filters['from'])) $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        if (! empty($filters['to'])) $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());

        return $query->latest()->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
