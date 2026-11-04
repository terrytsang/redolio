<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_direction_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('direction_key', 64);
            $table->unsignedTinyInteger('rank');
            $table->json('score_json')->nullable();
            $table->text('rationale')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_direction_recommendations');
    }
};
