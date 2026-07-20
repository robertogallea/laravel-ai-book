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
            // The month this request is for, in "Y-m" form (e.g.
            // "2026-07"), fixed at the moment the request is made: the
            // batch that eventually processes it must answer for this
            // month, not whatever month happens to be current by the
            // time it actually runs.
            $table->string('month');
            // "pending" until ProcessReportQueueCommand's next scheduled
            // run picks it up; "processed" afterward. There is
            // deliberately no per-request output stored here: every
            // pending request for the same month describes the exact
            // same report, so one run of GenerateMonthlyReportCommand
            // answers all of them.
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
