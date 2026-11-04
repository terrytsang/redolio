<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REDO v1.6 — additive only: nothing existing is altered. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positioning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('business_category')->nullable();
            $table->string('business_model', 64)->nullable();
            $table->json('target_audiences_json')->nullable();
            $table->json('core_offerings_json')->nullable();
            $table->json('customer_problems_json')->nullable();
            $table->json('customer_benefits_json')->nullable();
            $table->json('differentiators_json')->nullable();
            $table->json('geographic_scope_json')->nullable();
            $table->json('proof_points_json')->nullable();
            $table->json('unknowns_json')->nullable();
            $table->json('contradictions_json')->nullable();
            $table->json('existing_hero_json')->nullable();
            $table->string('brand_voice')->nullable();
            $table->string('conversion_goal', 32)->nullable();
            $table->string('status', 32)->default('building'); // building|ready|needs_confirmation|failed
            $table->string('source_mode', 32)->default('dna');  // dna|dna+model
            $table->string('model_identifier')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['analysis_run_id', 'version']);
            $table->index('status');
        });

        Schema::create('positioning_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('positioning_profile_id')->constrained('positioning_profiles')->cascadeOnDelete();
            $table->string('claim_key', 64);
            $table->string('field', 32);            // category|business_model|offering|audience|benefit|problem|differentiator|geography|proof|years|locations|certification|metric
            $table->text('claim_value');
            $table->string('status', 24);           // verified_source|user_confirmed|inferred|unknown|contradicted
            $table->string('confidence', 12)->default('low');
            $table->string('source_url', 2048)->nullable();
            $table->unsignedBigInteger('source_page_id')->nullable();
            $table->json('evidence_keys_json')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->timestamp('user_confirmed_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['positioning_profile_id', 'claim_key']);
            $table->index(['positioning_profile_id', 'status']);
        });

        Schema::create('hero_copy_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('positioning_profile_id')->constrained('positioning_profiles')->cascadeOnDelete();
            $table->string('batch', 40)->nullable();
            $table->string('strategy_id', 40);
            $table->string('eyebrow', 120)->nullable();
            $table->string('headline', 240);
            $table->text('description')->nullable();
            $table->json('primary_cta_json')->nullable();
            $table->json('secondary_cta_json')->nullable();
            $table->json('trust_signals_json')->nullable();
            $table->json('evidence_ids_json')->nullable();
            $table->json('assumptions_json')->nullable();
            $table->json('risks_json')->nullable();
            $table->json('score_breakdown_json')->nullable();
            $table->decimal('total_score', 5, 1)->nullable();
            $table->string('recommendation', 16)->nullable();   // recommended|needs_review|rewrite|rejected
            $table->string('hard_gate_status', 8)->default('pending'); // pending|pass|fail
            $table->json('validation_json')->nullable();
            $table->text('rationale')->nullable();
            $table->string('status', 16)->default('candidate'); // candidate|approved|rejected|superseded
            $table->string('source', 16)->default('model');    // model|user_edit|conservative|refinement
            $table->unsignedBigInteger('parent_option_id')->nullable();
            $table->unsignedBigInteger('design_spec_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['analysis_run_id', 'status']);
            $table->index('batch');
        });

        Schema::create('hero_copy_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('hero_copy_option_id')->constrained('hero_copy_options')->cascadeOnDelete();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->string('approval_status', 16);              // approved|rejected|layout_exception
            $table->json('user_edits_json')->nullable();
            $table->unsignedBigInteger('design_spec_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->index(['analysis_run_id', 'approval_status']);
        });

        Schema::create('hero_render_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('hero_copy_option_id')->constrained('hero_copy_options')->cascadeOnDelete();
            $table->unsignedBigInteger('design_spec_id')->nullable();
            $table->unsignedTinyInteger('attempt')->default(0);
            $table->json('viewport_json')->nullable();
            $table->boolean('font_loaded')->default(false);
            $table->unsignedTinyInteger('headline_line_count')->nullable();
            $table->unsignedTinyInteger('description_line_count')->nullable();
            $table->boolean('overflow_detected')->default(false);
            $table->boolean('clipping_detected')->default(false);
            $table->boolean('cta_visible')->default(false);
            $table->string('screenshot_path')->nullable();
            $table->string('validation_result', 16);            // pass|fail|unavailable|exception
            $table->json('details_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['analysis_run_id', 'hero_copy_option_id'], 'hrv_run_option_idx');
        });

        Schema::create('hero_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->string('kind', 16);                         // positioning|options|render
            $table->string('status', 16)->default('pending');  // pending|running|complete|failed
            $table->string('idempotency_key', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['analysis_run_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['hero_generations', 'hero_render_validations', 'hero_copy_approvals', 'hero_copy_options', 'positioning_evidence', 'positioning_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
