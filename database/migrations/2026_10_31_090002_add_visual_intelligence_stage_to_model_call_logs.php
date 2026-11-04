<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts model_call_logs.stage from a fixed MySQL ENUM to a plain string
 * column. The upgrade PRD adds new reasoning stages over time (starting
 * with website_visual_intelligence); a growing ENUM is brittle across
 * every supported DB driver (ALTER...MODIFY is MySQL-only syntax, and
 * SQLite's enum() emulation is a CHECK constraint that can't be widened
 * in place). The stage value is still validated against a known set by
 * the calling code (HttpMetaModelClient/StageSchemas), so this loses no
 * real safety.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') { // MySQL 8 keeps foreign-key names across a rename, so rebuild-by-copy collides: widen in place instead
            DB::statement('ALTER TABLE model_call_logs MODIFY stage VARCHAR(64) NOT NULL');

            return;
        }

        Schema::rename('model_call_logs', 'model_call_logs_old');

        Schema::create('model_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('stage', 64);
            $table->string('provider')->default('meta');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id', 'mcl_v2_analysis_run_id_index');
            $table->index('stage', 'mcl_v2_stage_index');
            $table->index('created_at', 'mcl_v2_created_at_index');
        });

        DB::statement('INSERT INTO model_call_logs (id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at)
            SELECT id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at FROM model_call_logs_old');

        Schema::drop('model_call_logs_old');
    }

    public function down(): void
    {
        Schema::rename('model_call_logs', 'model_call_logs_new');

        Schema::create('model_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->enum('stage', [
                'website_understanding', 'diagnosis', 'redesign_recommendations', 'evaluation', 'next_redo_optional',
            ]);
            $table->string('provider')->default('meta');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
            $table->index('stage');
            $table->index('created_at');
        });

        DB::statement("INSERT INTO model_call_logs (id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at)
            SELECT id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at FROM model_call_logs_new
            WHERE stage != 'website_visual_intelligence'");

        Schema::drop('model_call_logs_new');
    }
};
