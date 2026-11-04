<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('direction_key', 64);
            $table->unsignedInteger('direction_version')->default(1);
            $table->json('preferences_json')->nullable();
            $table->timestamp('selected_at')->nullable();

            $table->index('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_selections');
    }
};
