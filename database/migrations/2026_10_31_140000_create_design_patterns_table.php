<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design Trend Intelligence (self-evolving design taste, sibling of the
 * design_directions catalogue). Entries here are model-synthesized
 * pattern descriptions grounded by the Meta API's live web_search tool —
 * never scraped HTML, never copied images/screenshots. Each entry must be
 * a transformed/generalized summary with source domains cited, not a
 * reproduction of a specific copyrighted design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_type', 32); // layout | typography | color | component | hero | interaction
            $table->string('title');
            $table->text('description');
            $table->json('applicable_directions_json'); // subset of design_directions.direction_key, or ["general"]
            $table->json('source_domains_json')->nullable(); // e.g. ["siteinspire.com"] — domain-level attribution only
            $table->string('confidence', 16)->default('medium');
            $table->uuid('scout_batch_id');
            $table->timestamp('scouted_at');

            $table->index('pattern_type');
            $table->index('scout_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_patterns');
    }
};
