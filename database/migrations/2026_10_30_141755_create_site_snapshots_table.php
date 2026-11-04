<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('snapshot_hash', 64);
            $table->string('homepage_url', 2048);
            $table->unsignedTinyInteger('page_count')->default(0);
            $table->json('snapshot_json');
            $table->string('screenshot_path')->nullable();
            $table->json('warnings_json')->nullable();
            $table->timestamps();

            $table->index('analysis_run_id');
            $table->index('snapshot_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_snapshots');
    }
};
