<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('submitted_url', 2048);
            $table->string('normalized_url', 2048);
            $table->string('idempotency_key')->nullable()->unique();
            $table->enum('status', ['pending', 'running', 'partial', 'complete', 'failed'])->default('pending');
            $table->enum('current_stage', [
                'validating_url',
                'crawling',
                'building_dna',
                'diagnosing',
                'preparing_redesign',
                'evaluating',
                'ready',
            ])->default('validating_url');
            $table->enum('source_mode', ['live', 'fixture'])->default('live');
            $table->string('error_code')->nullable();
            $table->string('safe_error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('status');
            // MySQL 8 refuses a full-length index on a 2048-character column: index the first 191 characters there.
            DB::getDriverName() === 'mysql' ? $table->rawIndex('normalized_url(191)', 'analysis_runs_normalized_url_index') : $table->index('normalized_url');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_runs');
    }
};
