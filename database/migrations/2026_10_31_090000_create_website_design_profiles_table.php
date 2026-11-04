<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_design_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->json('profile_json');
            $table->unsignedInteger('profile_version')->default(1);
            $table->string('model_identifier')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_design_profiles');
    }
};
