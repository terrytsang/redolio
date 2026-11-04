<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->string('notify_email', 255)->nullable();
            $table->timestamp('notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notified_at']);
        });
    }
};
