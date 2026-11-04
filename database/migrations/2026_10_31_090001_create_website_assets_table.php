<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('asset_key', 64);
            $table->string('source_page_url', 2048);
            $table->string('original_url', 2048);
            $table->string('stored_path')->nullable();
            $table->enum('asset_type', [
                'logo', 'favicon', 'profile_portrait', 'team_portrait', 'hero_image',
                'product_image', 'service_image', 'portfolio_image', 'case_study_image',
                'testimonial_portrait', 'background_image', 'illustration', 'icon', 'other',
            ])->default('other');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('nearby_heading')->nullable();
            $table->string('nearby_text')->nullable();
            $table->string('dom_context')->nullable();
            $table->string('detected_role')->nullable();
            $table->decimal('relevance_score', 3, 2)->nullable();
            $table->enum('preservation_priority', ['low', 'medium', 'high'])->default('low');
            $table->string('recommended_usage')->nullable();
            $table->enum('recommended_action', [
                'preserve', 'reuse', 'reposition', 'crop', 'replace', 'omit', 'needs_user_review',
            ])->default('needs_user_review');
            $table->enum('usage_status', ['pending', 'selected', 'excluded', 'used'])->default('pending');
            $table->string('rights_status')->default('unknown');
            $table->string('download_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('analysis_run_id');
            $table->unique(['analysis_run_id', 'asset_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_assets');
    }
};
