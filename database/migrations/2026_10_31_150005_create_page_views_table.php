<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal first-party page-view log for the admin "daily visits" stat.
 * Stores a hashed IP (never the raw address) and no cross-site tracking
 * of any kind. Retention/pruning of old rows is a configurable concern
 * for later, consistent with the project's general retention-limit
 * principle for logs — not needed for the dashboard to work correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 2048);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
