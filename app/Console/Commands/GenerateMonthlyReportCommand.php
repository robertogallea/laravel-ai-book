<?php

namespace App\Console\Commands;

use App\Ai\Agents\MonthlyReportAdvisor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:generate-monthly-report')]
#[Description("Generate this month's spending report")]
class GenerateMonthlyReportCommand extends Command
{
    /**
     * Execute the console command.
     *
     * A single autonomous goal, "produce this month's report": the
     * assistant decides for itself which categories are worth a
     * GetBudgetStatusTool call and how many to make before concluding.
     * Nothing here checks that every category configured in
     * config('finance.budgets') was actually covered.
     */
    public function handle(): int
    {
        $response = (new MonthlyReportAdvisor)->prompt("Generate this month's spending report.");

        $summary = $response->structured['summary'] ?? null;

        // The schema declares "summary" required, but that does not
        // guarantee the model's response actually contains it (see the
        // chapter on structured output): trust nothing that was not
        // actually checked.
        if (! is_string($summary) || $summary === '') {
            $this->components->error('The assistant returned an incomplete report. Please try again in a moment.');

            return Command::FAILURE;
        }

        $this->line($summary);

        return Command::SUCCESS;
    }
}
