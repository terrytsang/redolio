<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Design Trend Intelligence scouting is a system-level job, not tied to
 * any specific analysis run — so model_call_logs.analysis_run_id must be
 * nullable. Same rename-and-rebuild approach as the earlier stage-enum
 * migration (portable across MySQL/SQLite; no ALTER...MODIFY).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') { // alter in place (see the previous model_call_logs migration)
            DB::statement('ALTER TABLE model_call_logs DROP FOREIGN KEY model_call_logs_analysis_run_id_foreign');
            DB::statement('ALTER TABLE model_call_logs MODIFY analysis_run_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE model_call_logs ADD CONSTRAINT model_call_logs_analysis_run_id_foreign FOREIGN KEY (analysis_run_id) REFERENCES analysis_runs (id) ON DELETE SET NULL');

            return;
        }

        Schema::rename('model_call_logs', 'model_call_logs_old');

        Schema::create('model_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
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

            $table->index('analysis_run_id', 'mcl_v3_analysis_run_id_index');
            $table->index('stage', 'mcl_v3_stage_index');
            $table->index('created_at', 'mcl_v3_created_at_index');
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

            $table->index('analysis_run_id');
            $table->index('stage');
            $table->index('created_at');
        });

        DB::statement('INSERT INTO model_call_logs (id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at)
            SELECT id, analysis_run_id, stage, provider, model_identifier, prompt_version, request_hash, response_status, latency_ms, input_tokens, output_tokens, error_code, created_at FROM model_call_logs_new
            WHERE analysis_run_id IS NOT NULL');

        Schema::drop('model_call_logs_new');
    }
};
