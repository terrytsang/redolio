<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_direction_generations', function (Blueprint $table) {
            $table->id();
            $table->string('requested_name');
            $table->string('direction_key', 64);
            $table->string('source_url', 2048);
            $table->string('status', 16)->default('pending'); // pending | running | complete | failed
            $table->string('error_message')->nullable();
            $table->foreignId('analysis_run_id')->nullable()->constrained('analysis_runs')->nullOnDelete();
            $table->foreignId('design_direction_id')->nullable()->constrained('design_directions')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_direction_generations');
    }
};
