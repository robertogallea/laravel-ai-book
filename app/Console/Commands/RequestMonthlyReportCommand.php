<?php

namespace App\Console\Commands;

use App\Models\ReportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:request-monthly-report')]
#[Description("Queue a request for this month's report, for later, batched delivery instead of an immediate one")]
class RequestMonthlyReportCommand extends Command
{
    /**
     * Execute the console command.
     *
     * Unlike GenerateMonthlyReportCommand, invoked directly by someone who
     * wants the report right now, this command never runs the pipeline
     * itself: it only records that a report is wanted, for
     * ProcessReportQueueCommand's next scheduled run to pick up alongside
     * whatever else is pending.
     */
    public function handle(): int
    {
        ReportRequest::create(['status' => 'pending']);

        $this->components->info('Report queued. You will receive it at the next scheduled batch, not immediately.');

        return Command::SUCCESS;
    }
}
