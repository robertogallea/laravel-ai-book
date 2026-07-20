<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `constrained()` alone adds the foreign key constraint, not an
     * index to look up by it: MySQL happens to add one automatically for
     * a foreign key column, SQLite and Postgres do not. Every one of
     * these columns is now the mandatory filter on its table (see
     * Transaction::scopeOwnedBy and MemoryFact::scopeOwnedBy), so a
     * lookup by user is worth an explicit index regardless of which
     * database happens to be configured.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('memory_facts', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('report_requests', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('memory_facts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('report_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
