<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Hand-edited hero copy has no generated option, but its rendered fit must still be recorded.
    public function up(): void
    {
        Schema::table('hero_render_validations', function (Blueprint $table) {
            $table->unsignedBigInteger('hero_copy_option_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hero_render_validations', function (Blueprint $table) {
            $table->unsignedBigInteger('hero_copy_option_id')->nullable(false)->change();
        });
    }
};
