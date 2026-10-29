<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class TestDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;
    public function test_tests_use_in_memory_sqlite_not_the_development_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
    public function test_migrations_build_the_cms_schema_with_foreign_keys_enforced(): void
    {
        foreach (['users', 'pages', 'page_sections', 'page_revisions', 'content_items', 'media_assets', 'menus', 'menu_items', 'settings', 'enquiries', 'redirects'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
        $this->assertSame(1, (int) DB::selectOne('pragma foreign_keys')->foreign_keys);
    }
    public function test_templates_compile_into_the_runs_temporary_folder_not_the_sites_storage(): void
    {
        $compiled = config('view.compiled');
        $this->assertStringStartsWith(sys_get_temp_dir().'/kelika-phpunit-views-', $compiled);
        $this->assertDirectoryExists($compiled);
        $site = glob(storage_path('framework/views').'/*.php') ?: [];
        $siteState = array_map(fn ($f) => [$f, filemtime($f)], $site);
        $this->withoutVite()->get('/admin/login')->assertOk();
        $this->assertNotEmpty(glob($compiled.'/*.php'), 'the login page was compiled into the test folder');
        $this->assertSame($siteState, array_map(fn ($f) => [$f, filemtime($f)], glob(storage_path('framework/views').'/*.php') ?: []), 'the website\'s compiled views were not touched');
    }
}
