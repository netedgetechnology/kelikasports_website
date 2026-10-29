<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run any test unless it is isolated on in-memory SQLite. This runs before RefreshDatabase and
     * similar traits, so a missing phpunit.xml override or a cached config can never reach the MariaDB database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $default = (string) $app['config']->get('database.default');
        $connection = (array) $app['config']->get("database.connections.{$default}", []);
        if (! $app->environment('testing') || ($connection['driver'] ?? null) !== 'sqlite' || ($connection['database'] ?? null) !== ':memory:' || ! empty($connection['url'])) {
            throw new RuntimeException("Tests must use in-memory SQLite (APP_ENV=testing). Got environment [{$app->environment()}], connection [{$default}]. Check phpunit.xml and run `php artisan config:clear` if config is cached.");
        }
        // Likewise for compiled templates: they belong in the run's temporary folder (tests/bootstrap.php), so tests
        // never write files into the website's own storage/framework/views.
        $compiled = (string) $app['config']->get('view.compiled');
        $siteViews = realpath($app->storagePath('framework/views')) ?: $app->storagePath('framework/views');
        if ($compiled === '' || str_starts_with(realpath($compiled) ?: $compiled, $siteViews)) {
            throw new RuntimeException("Tests must compile views outside storage/framework/views. Got [{$compiled}]. Run them through phpunit.xml (bootstrap tests/bootstrap.php).");
        }
        return $app;
    }
}
