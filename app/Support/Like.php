<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Partial-text search where everything typed is matched literally: "%" and "_" mean themselves, not "anything".
 * "!" is the escape character because it means the same in MySQL/MariaDB and SQLite, unlike a backslash.
 */
class Like
{
    /** The LIKE pattern for "contains $term". */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /** Adds "(column1 LIKE … OR column2 LIKE …)" for $term to the query. */
    public static function anyColumn(Builder|EloquentBuilder $query, array $columns, string $term): Builder|EloquentBuilder
    {
        $pattern = self::contains($term);

        return $query->where(function ($builder) use ($columns, $pattern) {
            foreach ($columns as $column) $builder->orWhereRaw($builder->getGrammar()->wrap($column)." like ? escape '!'", [$pattern]);
        });
    }
}
