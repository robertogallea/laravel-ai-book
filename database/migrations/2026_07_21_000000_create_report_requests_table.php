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
        Schema::create('report_requests', function (Blueprint $table) {
            $table->id();
            // "pending" until ProcessReportQueueCommand's next scheduled
            // run picks it up; "processed" afterward. There is
            // deliberately no per-request output stored here: every
            // pending request describes the exact same report, so one
            // run of GenerateMonthlyReportCommand answers all of them.
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_requests');
    }
};
