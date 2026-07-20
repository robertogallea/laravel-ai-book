<?php

namespace App\Console\Commands;

use App\Models\ReportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:process-report-queue')]
#[Description('Process every pending monthly-report request in a single scheduled batch')]
class ProcessReportQueueCommand extends Command
{
    /**
     * Execute the console command.
     *
     * However many times this month's report was requested since the
     * last run, pending or not, every one of those requests describes the
     * exact same report: the pipeline behind it only needs to run once.
     * Requests are marked "processed" only after that single run actually
     * succeeds, so a failure here leaves them pending for the next
     * scheduled attempt instead of silently dropping them.
     */
    public function handle(): int
    {
        $pendingCount = ReportRequest::where('status', 'pending')->count();

        if ($pendingCount === 0) {
            $this->components->info('No report requests pending.');

            return Command::SUCCESS;
        }

        $exitCode = $this->call(GenerateMonthlyReportCommand::class);

        if ($exitCode !== Command::SUCCESS) {
            return $exitCode;
        }

        ReportRequest::where('status', 'pending')->update(['status' => 'processed']);

        $this->components->info("Processed {$pendingCount} pending report request(s) in a single batched run.");

        return Command::SUCCESS;
    }
}
