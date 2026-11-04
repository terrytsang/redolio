<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_redo_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('design_spec_id')->nullable()->constrained('design_specs')->nullOnDelete();
            $table->json('signal_summary_json');
            $table->text('recommendation');
            $table->text('rationale');
            $table->string('hypothesis_label')->default('hypothesis_not_yet_tested');
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_redo_recommendations');
    }
};
