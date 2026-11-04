<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_assets', function (Blueprint $table) {
            // 'original' = captured from the business's own site (the
            // default, always preferred). 'stock_photo' = a free licensed
            // stock photo fetched only when no suitable original image
            // exists for a hero/section slot, so the redesign never ships
            // an empty placeholder box when the source site had little or
            // no usable imagery.
            $table->string('asset_origin', 16)->default('original')->after('asset_type');
            $table->string('attribution')->nullable()->after('asset_origin');
        });
    }

    public function down(): void
    {
        Schema::table('website_assets', function (Blueprint $table) {
            $table->dropColumn(['asset_origin', 'attribution']);
        });
    }
};
