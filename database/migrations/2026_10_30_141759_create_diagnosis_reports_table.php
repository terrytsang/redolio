<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('summary');
            $table->json('issues_json');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
            $table->unique(['analysis_run_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_reports');
    }
};
