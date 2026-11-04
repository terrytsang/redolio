<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_snapshot_id')->constrained('site_snapshots')->cascadeOnDelete();
            $table->string('evidence_key', 64);
            $table->string('page_url', 2048);
            $table->enum('evidence_type', [
                'title', 'meta', 'heading', 'paragraph', 'cta', 'form',
                'link', 'image_alt', 'contact', 'technical', 'crawl_warning',
            ]);
            $table->string('label');
            $table->text('excerpt');
            $table->json('locator_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('site_snapshot_id');
            $table->unique(['site_snapshot_id', 'evidence_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_items');
    }
};
