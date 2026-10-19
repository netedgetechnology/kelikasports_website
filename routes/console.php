<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('kelika:about', function () {
    $this->info('KELIKA Website & CMS. See docs/IMPLEMENTATION_STATUS.md for the current build status.');
})->purpose('Display KELIKA project information');
