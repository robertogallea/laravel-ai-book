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
        Schema::table('memory_facts', function (Blueprint $table) {
            // Same reasoning as transactions.user_id: nullable, not
            // backfilled, so a fact remembered before this migration
            // stays unowned rather than being retroactively assigned.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_facts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
