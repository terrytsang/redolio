<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_key', 32)->unique();
            $table->string('name');
            $table->text('description');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Category membership is a discovery default, not a constraint (PRD v1.5
        // cross_category_rule) — so these are JSON lists, never foreign keys.
        Schema::table('design_directions', function (Blueprint $table) {
            $table->json('primary_category_ids')->nullable();
            $table->json('compatible_category_ids')->nullable();
            $table->string('token_profile_id', 64)->nullable();
            $table->string('motion_profile_id', 64)->nullable();
            $table->string('replaced_by_key', 64)->nullable();
            $table->string('catalogue_version', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('design_directions', function (Blueprint $table) {
            $table->dropColumn([
                'primary_category_ids', 'compatible_category_ids', 'token_profile_id',
                'motion_profile_id', 'replaced_by_key', 'catalogue_version',
            ]);
        });
        Schema::dropIfExists('design_categories');
    }
};
