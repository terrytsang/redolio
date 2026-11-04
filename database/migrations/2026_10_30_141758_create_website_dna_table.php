<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_dna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('dna_json');
            $table->string('model_identifier')->nullable();
            $table->string('prompt_version')->nullable();
            $table->boolean('edited_by_user')->default(false);
            $table->timestamps();

            $table->index('analysis_run_id');
            $table->unique(['analysis_run_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_dna');
    }
};
