<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('model_call_logs');
    }
};
