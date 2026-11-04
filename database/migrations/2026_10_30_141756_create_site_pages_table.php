<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_snapshot_id')->constrained('site_snapshots')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->json('headings_json')->nullable();
            $table->longText('visible_text')->nullable();
            $table->json('links_json')->nullable();
            $table->json('ctas_json')->nullable();
            $table->json('forms_json')->nullable();
            $table->json('image_alt_json')->nullable();
            $table->enum('fetch_status', ['success', 'partial', 'failed'])->default('success');
            $table->timestamps();

            $table->index('site_snapshot_id');
            DB::getDriverName() === 'mysql' ? $table->rawIndex('url(191)', 'site_pages_url_index') : $table->index('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
