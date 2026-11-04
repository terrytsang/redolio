<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->foreignId('refine_from_design_spec_id')->nullable()->after('design_spec_id')->constrained('design_specs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refine_from_design_spec_id');
        });
    }
};
