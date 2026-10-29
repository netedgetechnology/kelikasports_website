<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('kelika:about', function () {
    $this->info('KELIKA Website & CMS. See docs/IMPLEMENTATION_STATUS.md for the current build status.');
})->purpose('Display KELIKA project information');

// Scheduled page publishing. On cPanel, Laravel's scheduler needs one cron entry, every minute:
//   * * * * * cd /home/kelikasports/public_html && php artisan schedule:run >> /dev/null 2>&1
// Pages appear on time even if cron is late (visibility follows the clock); this records them as published.
Schedule::command('kelika:publish-scheduled')->everyMinute()->withoutOverlapping(10);
