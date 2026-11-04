<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Self-evolving website" mechanism: a user registers a site once, and
 * REDO automatically re-runs the full AnalyzeWebsiteAction pipeline on a
 * schedule tied to their plan's auto_rescan_frequency_days, so their
 * Website DNA/diagnosis/redesign preview stays current without them
 * manually resubmitting the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('submitted_url', 2048);
            $table->string('normalized_url', 2048);
            $table->unsignedInteger('frequency_days')->default(30);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('next_scan_at')->nullable();
            $table->foreignId('last_analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('next_scan_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_watches');
    }
};
