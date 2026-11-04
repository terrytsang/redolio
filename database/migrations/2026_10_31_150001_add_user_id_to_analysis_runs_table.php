<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            // Nullable: the public landing-page "Analyze" flow and demo
            // fixtures remain usable without an account.
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
