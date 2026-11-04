<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('template_key');
            $table->json('design_spec_json');
            $table->enum('status', ['pending', 'valid', 'rejected'])->default('pending');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamps();

            $table->index('analysis_run_id');
            $table->unique(['analysis_run_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_specs');
    }
};
