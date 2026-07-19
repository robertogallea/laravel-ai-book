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
        Schema::create('memory_facts', function (Blueprint $table) {
            $table->id();
            // A short, self-contained statement extracted from a past
            // exchange with the assistant, e.g. a stated savings goal:
            // distinct from a transaction, this table exists only because
            // a conversation happened, not because of anything already on
            // record elsewhere in the application.
            $table->text('content');
            // Computed synchronously when the fact is first persisted,
            // unlike App\Models\Transaction's embedding column: there is
            // no separate indexing command for memory facts, since a fact
            // is never useful before it can be retrieved by meaning.
            $table->json('embedding');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_facts');
    }
};
