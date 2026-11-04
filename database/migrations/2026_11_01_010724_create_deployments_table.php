<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_spec_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->unsignedInteger('deployment_number');
            $table->string('status', 16)->default('pending'); // pending|validating|live|failed|rolled_back
            $table->string('artifact_path')->nullable();
            $table->string('artifact_hash', 64)->nullable();
            $table->json('validation_json')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['analysis_run_id', 'deployment_number']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
