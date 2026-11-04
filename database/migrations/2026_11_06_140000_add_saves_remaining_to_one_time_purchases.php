<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An Export Pass covers one website: its first download plus two more, and two more saved edits (Starter/Agency are unlimited).
        Schema::table('one_time_purchases', function (Blueprint $table) {
            $table->unsignedInteger('saves_remaining')->default(2)->after('exports_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('one_time_purchases', fn (Blueprint $t) => $t->dropColumn('saves_remaining'));
    }
};
