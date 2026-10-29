<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Canonical form of a site path for storing and matching redirect sources: one leading slash, lowercase, no
     * trailing slash. Matching is case-insensitive on every database, and the web server already strips trailing
     * slashes from requests.
     */
    public static function normalizePath(?string $path): string
    {
        $path = strtolower(trim((string) $path));

        return '/'.trim($path, '/');
    }

    /** The active redirect for a requested site path, if any. */
    public static function forPath(string $path): ?self
    {
        return static::query()->where('from_path', static::normalizePath($path))->where('is_active', true)->first();
    }
}
