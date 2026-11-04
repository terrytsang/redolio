<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->enum('stage', [
                'validating_url',
                'crawling',
                'building_dna',
                'diagnosing',
                'preparing_redesign',
                'evaluating',
                'ready',
            ]);
            $table->enum('status', ['running', 'complete', 'partial', 'failed']);
            $table->string('message_code')->nullable();
            $table->string('safe_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata_json')->nullable();

            $table->index('analysis_run_id');
            $table->index('stage');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_stage_logs');
    }
};
