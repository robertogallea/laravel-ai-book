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
        Schema::create('eval_feedback', function (Blueprint $table) {
            $table->id();
            // The expense description that was categorized, and the
            // category the assistant actually returned for it: together,
            // the interaction a negative rating is flagging.
            $table->text('input');
            $table->string('category');
            // Stays "pending_review" until a human confirms or dismisses
            // it (see ReviewFeedbackCommand): a rating never sets this to
            // "confirmed" by itself.
            $table->string('status')->default('pending_review');
            // Filled in only once a reviewer confirms the case: the
            // category the response should have returned. Null for every
            // pending or dismissed row.
            $table->string('expected_category')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eval_feedback');
    }
};
