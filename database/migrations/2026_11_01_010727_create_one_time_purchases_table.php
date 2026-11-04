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
        Schema::create('one_time_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_type', 32)->default('export_pass');
            $table->string('status', 16)->default('paid'); // data-model only — no real payment provider wired up
            $table->unsignedInteger('exports_remaining')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_purchases');
    }
};
