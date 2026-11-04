<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_directions', function (Blueprint $table) {
            $table->id();
            $table->string('direction_key', 64);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('recipe_json');
            $table->boolean('is_selectable')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique('direction_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_directions');
    }
};
