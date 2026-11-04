<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('design_spec_id')->constrained('design_specs')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('render_status')->default('improved');
            $table->json('quality_report_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_versions');
    }
};
