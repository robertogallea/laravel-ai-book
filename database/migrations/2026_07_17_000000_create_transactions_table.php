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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('merchant');
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->date('occurred_at');
            // Null until App\Console\Commands\IndexTransactionsCommand computes
            // it: a transaction can exist, and be retrieved by exact filters,
            // before it is ever indexed for semantic search.
            $table->json('embedding')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
