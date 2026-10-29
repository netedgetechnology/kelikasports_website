<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Records scheduled pages as published once their time has come. Visitors can already see such a page from the
 * moment published_at passes (see Page::scopePublished), so this never decides visibility; it turns "scheduled" into
 * "published", keeps a revision of the change, and clears the cached menus. Run every minute by the scheduler
 * (routes/console.php). Safe to run any number of times: each page is locked, re-checked and changed at most once.
 */
class PublishScheduledPages extends Command
{
    protected $signature = 'kelika:publish-scheduled';

    protected $description = 'Record scheduled pages whose publication time has passed as published';

    public function handle(): int
    {
        $published = 0;
        // Ids in batches, so a long backlog is never loaded into memory at once. Archived pages are never selected.
        Page::query()->dueForPublication()->select('id')->chunkById(100, function ($pages) use (&$published) {
            foreach ($pages as $candidate) {
                $published += (int) DB::transaction(function () use ($candidate) {
                    // Re-read under a lock: another run, or an administrator, may have changed it meanwhile.
                    $page = Page::query()->dueForPublication()->whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $page) return false;

                    PageRevision::create(['page_id' => $page->id, 'user_id' => null, 'revision_label' => 'Before scheduled publication', 'snapshot' => $page->revisionSnapshot()]);
                    // The scheduled time stays the publication date; saving clears the cached public menus.
                    $page->update(['status' => Page::PUBLISHED]);
                    $this->line("Published /{$page->slug} (scheduled for {$page->published_at->format('Y-m-d H:i')}).");

                    return true;
                });
            }
        });

        $this->info($published ? "{$published} scheduled ".str('page')->plural($published).' published.' : 'No scheduled pages are due.');

        return self::SUCCESS;
    }
}
