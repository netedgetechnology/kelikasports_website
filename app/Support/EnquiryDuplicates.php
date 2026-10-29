<?php

namespace App\Support;

use App\Models\Enquiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds possible duplicate enquiries: the same email address (ignoring case and surrounding spaces) and the same
 * message (ignoring case and differences in spacing or line breaks), received within WINDOW_DAYS of each other.
 * Duplicates are only pointed out in the CMS; every submission is still stored and handled on its own.
 */
class EnquiryDuplicates
{
    /** Long enough to catch a double submit or a "did you get my message?" resend, short enough not to link unrelated enquiries. */
    public const WINDOW_DAYS = 7;

    /**
     * Possible duplicates of each given enquiry, keyed by its id, using one query whatever the number of enquiries.
     * Spam counts as a match; deleted enquiries do not.
     *
     * @param  iterable<Enquiry>  $enquiries
     * @return array<int, Collection<int, Enquiry>>
     */
    public static function for(iterable $enquiries): array
    {
        $enquiries = collect($enquiries)->filter(fn ($e) => $e->created_at);
        if ($enquiries->isEmpty()) return [];

        $window = self::WINDOW_DAYS;
        $candidates = Enquiry::query()
            ->whereIn(DB::raw('lower(trim(email))'), $enquiries->map(fn ($e) => self::email($e->email))->unique()->values()->all())
            ->whereBetween('created_at', [$enquiries->min('created_at')->copy()->subDays($window), $enquiries->max('created_at')->copy()->addDays($window)])
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'email', 'message', 'status', 'created_at'])
            ->groupBy(fn ($e) => self::key($e));

        return $enquiries->mapWithKeys(fn ($e) => [$e->id => ($candidates[self::key($e)] ?? collect())
            ->filter(fn ($other) => $other->id !== $e->id && abs($other->created_at->diffInSeconds($e->created_at)) <= $window * 86400)
            ->values()])->filter(fn ($matches) => $matches->isNotEmpty())->all();
    }

    private static function key(Enquiry $enquiry): string
    {
        return self::email($enquiry->email).'|'.hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $enquiry->message))));
    }

    private static function email(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
