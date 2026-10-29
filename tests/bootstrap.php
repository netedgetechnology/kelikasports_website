<?php

/*
 * PHPUnit bootstrap. Each test run compiles Blade templates into its own temporary folder instead of the site's
 * storage/framework/views, so running the tests (often as a different user from the web server) never creates or
 * touches the compiled views the website itself uses. The folder is removed when the run ends; nothing else is.
 */

require __DIR__.'/../vendor/autoload.php';

$compiled = sys_get_temp_dir().'/kelika-phpunit-views-'.getmypid().'-'.bin2hex(random_bytes(4));
if (! mkdir($compiled, 0700, true) && ! is_dir($compiled)) {
    throw new RuntimeException("Could not create the test view folder {$compiled}.");
}
// Laravel reads $_SERVER, then $_ENV, then getenv(); set all three so config/view.php picks it up.
putenv("VIEW_COMPILED_PATH={$compiled}");
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $compiled;

register_shutdown_function(static function () use ($compiled) {
    // Only ever this run's own folder, and only its compiled .php files.
    if (! str_starts_with($compiled, sys_get_temp_dir().'/kelika-phpunit-views-') || ! is_dir($compiled)) return;
    foreach (glob($compiled.'/*.php') ?: [] as $file) @unlink($file);
    @rmdir($compiled);
});
