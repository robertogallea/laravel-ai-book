<?php

namespace App\Console\Commands;

use App\Models\ReportRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('assistant:process-report-queue')]
#[Description('Process every pending monthly-report request in a single scheduled batch')]
class ProcessReportQueueCommand extends Command
{
    /**
     * Execute the console command.
     *
     * A lock guards the whole run: two overlapping invocations of this
     * command (a scheduler retry, a manual run overlapping a scheduled
     * one) would otherwise both see the same pending requests and both
     * run the pipeline for them, exactly the duplicated cost batching
     * exists to avoid. Only one invocation at a time ever proceeds past
     * this point; the other exits immediately, having claimed nothing.
     */
    public function handle(): int
    {
        $lock = Cache::lock('process-report-queue', 300);

        if (! $lock->get()) {
            $this->components->warn('Another process-report-queue run is already in progress.');

            return Command::SUCCESS;
        }

        try {
            return $this->processPendingRequests();
        } finally {
            $lock->release();
        }
    }

    /**
     * However many times a given month's report was requested since the
     * last run, pending or not, every one of those requests describes the
     * exact same report: the pipeline behind it only needs to run once
     * per distinct month, not once per request. Only the requests
     * snapshotted here at the start are ever marked "processed", never a
     * fresh query against "status = pending": a request that arrives
     * while this run is still in progress is left for the next run
     * instead of being swept in without ever having been covered by this
     * one.
     */
    private function processPendingRequests(): int
    {
        $pending = ReportRequest::where('status', ReportRequest::STATUS_PENDING)->get();

        if ($pending->isEmpty()) {
            $this->components->info('No report requests pending.');

            return Command::SUCCESS;
        }

        $anyFailed = false;

        foreach ($pending->groupBy('month') as $month => $requestsForMonth) {
            $exitCode = $this->call(GenerateMonthlyReportCommand::class, ['month' => $month]);

            if ($exitCode !== Command::SUCCESS) {
                $anyFailed = true;

                continue;
            }

            ReportRequest::whereIn('id', $requestsForMonth->pluck('id'))->update(['status' => ReportRequest::STATUS_PROCESSED]);

            $this->components->info(sprintf(
                'Processed %d pending report request(s) for %s in a single batched run.',
                $requestsForMonth->count(),
                $month,
            ));
        }

        return $anyFailed ? Command::FAILURE : Command::SUCCESS;
    }
}
