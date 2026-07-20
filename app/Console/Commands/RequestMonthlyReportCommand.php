<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesUserOption;
use App\Models\ReportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:request-monthly-report {--user= : Email address of the user this report is for}')]
#[Description("Queue a request for this month's report, for later, batched delivery instead of an immediate one")]
class RequestMonthlyReportCommand extends Command
{
    use ResolvesUserOption;

    /**
     * Execute the console command.
     *
     * Unlike GenerateMonthlyReportCommand, invoked directly by someone who
     * wants the report right now, this command never runs the pipeline
     * itself: it only records that a report is wanted, for
     * ProcessReportQueueCommand's next scheduled run to pick up alongside
     * whatever else is pending. The current month is fixed here, at
     * request time, precisely so that a batch delayed past a month
     * boundary still answers for the month actually requested. The user
     * is fixed here for the same reason: whoever's report this batch
     * eventually generates must be decided now, not guessed later from
     * whichever request happens to be read first.
     */
    public function handle(): int
    {
        $user = $this->resolveUserOption();

        if ($user === false) {
            return Command::INVALID;
        }

        ReportRequest::create([
            'user_id' => $user->id,
            'month' => now()->format('Y-m'),
            'status' => ReportRequest::STATUS_PENDING,
        ]);

        $this->components->info('Report queued. You will receive it at the next scheduled batch, not immediately.');

        return Command::SUCCESS;
    }
}
