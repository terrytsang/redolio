<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('design_spec_id')->nullable()->constrained('design_specs')->nullOnDelete();
            $table->string('event_name');
            $table->string('element_key')->nullable();
            $table->string('session_token_hash', 64)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['analysis_run_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_events');
    }
};
