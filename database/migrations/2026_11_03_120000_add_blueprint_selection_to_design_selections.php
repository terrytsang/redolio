<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->string('category_id', 32)->nullable();
            $table->string('blueprint_id', 64)->nullable();
            $table->unsignedInteger('blueprint_version')->nullable();
            $table->string('catalogue_version', 16)->nullable();
            // Options shown vs chosen, for THIS project only (never used to train on customer data).
            $table->json('selection_context_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->dropColumn(['category_id', 'blueprint_id', 'blueprint_version', 'catalogue_version', 'selection_context_json']);
        });
    }
};
