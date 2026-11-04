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
            $table->string('status', 16)->default('generating')->after('preferences_json'); // generating | complete | failed
            $table->foreignId('design_spec_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->string('error_message')->nullable()->after('design_spec_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('design_selections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_spec_id');
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
