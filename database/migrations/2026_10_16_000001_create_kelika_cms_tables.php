<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); $table->string('role')->default('editor'); $table->boolean('is_active')->default(true); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) { $table->string('email')->primary(); $table->string('token'); $table->timestamp('created_at')->nullable(); });
        Schema::create('sessions', function (Blueprint $table) { $table->string('id')->primary(); $table->foreignId('user_id')->nullable()->index(); $table->string('ip_address',45)->nullable(); $table->text('user_agent')->nullable(); $table->longText('payload'); $table->integer('last_activity')->index(); });
        Schema::create('cache', function (Blueprint $table) { $table->string('key')->primary(); $table->mediumText('value'); $table->integer('expiration')->index(); });
        Schema::create('cache_locks', function (Blueprint $table) { $table->string('key')->primary(); $table->string('owner'); $table->integer('expiration')->index(); });
        Schema::create('jobs', function (Blueprint $table) { $table->id(); $table->string('queue')->index(); $table->longText('payload'); $table->unsignedTinyInteger('attempts'); $table->unsignedInteger('reserved_at')->nullable(); $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at'); });
        Schema::create('job_batches', function (Blueprint $table) { $table->string('id')->primary(); $table->string('name'); $table->integer('total_jobs'); $table->integer('pending_jobs'); $table->integer('failed_jobs'); $table->longText('failed_job_ids'); $table->mediumText('options')->nullable(); $table->integer('cancelled_at')->nullable(); $table->integer('created_at'); $table->integer('finished_at')->nullable(); });
        Schema::create('failed_jobs', function (Blueprint $table) { $table->id(); $table->string('uuid')->unique(); $table->text('connection'); $table->text('queue'); $table->longText('payload'); $table->longText('exception'); $table->timestamp('failed_at')->useCurrent(); });
        Schema::create('pages', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->string('slug')->unique(); $table->string('template')->default('standard'); $table->string('status')->default('draft')->index();
            $table->boolean('show_in_menu')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->string('seo_title')->nullable(); $table->string('seo_description',320)->nullable();
            $table->string('canonical_url')->nullable(); $table->string('robots_directive')->default('index,follow'); $table->string('og_title')->nullable(); $table->string('og_description',320)->nullable(); $table->foreignId('og_media_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('published_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id(); $table->foreignId('page_id')->constrained()->cascadeOnDelete(); $table->string('type'); $table->string('admin_label')->nullable(); $table->unsignedInteger('sort_order')->default(0); $table->boolean('is_visible')->default(true); $table->json('data'); $table->timestamps(); $table->index(['page_id','sort_order']);
        });
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id(); $table->foreignId('page_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('revision_label')->nullable(); $table->json('snapshot'); $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) { $table->id(); $table->string('key')->unique(); $table->json('value')->nullable(); $table->string('group')->default('general')->index(); $table->timestamps(); });
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id(); $table->string('disk')->default('public'); $table->string('path'); $table->string('original_name'); $table->string('mime_type',120); $table->unsignedBigInteger('size_bytes');
            $table->string('alt_text')->nullable(); $table->string('caption')->nullable(); $table->json('metadata')->nullable(); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->softDeletes();
        });
        Schema::table('pages', function (Blueprint $table) { $table->foreign('og_media_id')->references('id')->on('media_assets')->nullOnDelete(); });
        Schema::create('menus', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('location')->unique(); $table->boolean('is_active')->default(true); $table->timestamps(); });
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('menu_id')->constrained()->cascadeOnDelete(); $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('label'); $table->string('url'); $table->string('target')->default('_self'); $table->boolean('is_visible')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->index(['menu_id','sort_order']);
        });
        Schema::create('content_items', function (Blueprint $table) {
            $table->id(); $table->string('type')->index(); $table->string('title'); $table->string('slug')->nullable(); $table->string('status')->default('draft')->index(); $table->unsignedInteger('sort_order')->default(0);
            $table->json('data')->nullable(); $table->string('seo_title')->nullable(); $table->string('seo_description',320)->nullable(); $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->softDeletes(); $table->index(['type','status','sort_order']); $table->unique(['type','slug']);
        });
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email'); $table->string('phone')->nullable(); $table->string('category')->nullable(); $table->text('message'); $table->string('status')->default('new')->index();
            $table->text('internal_notes')->nullable(); $table->string('source_page')->nullable(); $table->string('ip_hash',64)->nullable(); $table->timestamp('consented_at')->nullable(); $table->timestamps();
        });
        Schema::create('redirects', function (Blueprint $table) { $table->id(); $table->string('from_path')->unique(); $table->string('to_path'); $table->unsignedSmallInteger('status_code')->default(301); $table->boolean('is_active')->default(true); $table->timestamps(); });
    }

    public function down(): void
    {
        foreach (['redirects','enquiries','content_items','menu_items','menus','page_revisions','page_sections','pages','settings','media_assets','failed_jobs','job_batches','jobs','cache_locks','cache','sessions','password_reset_tokens','users'] as $table) Schema::dropIfExists($table);
    }
};
