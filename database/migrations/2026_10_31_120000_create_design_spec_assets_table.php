<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_spec_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_spec_id')->constrained('design_specs')->cascadeOnDelete();
            $table->string('asset_key', 64);
            $table->string('component_role');
            $table->json('usage_json')->nullable();

            $table->index('design_spec_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_spec_assets');
    }
};
