<?php

namespace Tests\Feature;

use App\Ai\Agents\MonthlyReportSummarizer;
use App\Ai\Agents\OverspendingAdvisor;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_configured_category_reaches_the_summary_even_with_no_transactions(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00, 'entertainment' => 100.00]]);

        Transaction::factory()->create(['category' => 'groceries', 'amount' => 64.35, 'occurred_at' => now()]);
        // No "entertainment" transaction at all this month: the category
        // still has to reach the summarizer, at zero dollars spent,
        // unlike the agentic version where an uncalled category simply
        // never appears in the report at all.

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries and entertainment are both within budget.'],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('Groceries and entertainment are both within budget.')
            ->assertExitCode(0);

        MonthlyReportSummarizer::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'groceries: 64.35 of 400.00 dollars spent')
                && str_contains($prompt->prompt, 'entertainment: 0.00 of 100.00 dollars spent')
        );

        OverspendingAdvisor::assertNeverPrompted();
    }

    public function test_recommendations_are_skipped_when_no_category_is_over_budget(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        Transaction::factory()->create(['category' => 'groceries', 'amount' => 100.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries spending is well within budget this month.'],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('Groceries spending is well within budget this month.')
            ->assertExitCode(0);

        OverspendingAdvisor::assertNeverPrompted();
    }

    public function test_recommendations_run_only_for_categories_over_the_threshold(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00, 'entertainment' => 100.00]]);

        // Groceries: exactly 10% over budget, at the threshold but not
        // past it, does not trigger the extra step. Entertainment: 50%
        // over, well past it.
        Transaction::factory()->create(['category' => 'groceries', 'amount' => 440.00, 'occurred_at' => now()]);
        Transaction::factory()->create(['category' => 'entertainment', 'amount' => 150.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries and entertainment are both over budget this month.'],
        ]);

        OverspendingAdvisor::fake([
            ['recommendations' => 'Consider pausing discretionary entertainment purchases until next month.'],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('Groceries and entertainment are both over budget this month.')
            ->expectsOutputToContain('Consider pausing discretionary entertainment purchases until next month.')
            ->assertExitCode(0);

        OverspendingAdvisor::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'entertainment: 50% over budget')
                && ! str_contains($prompt->prompt, 'groceries')
        );
    }

    public function test_an_incomplete_summary_is_reported_instead_of_trusted(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        MonthlyReportSummarizer::fake([
            [],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('The assistant returned an incomplete summary')
            ->assertExitCode(1);

        OverspendingAdvisor::assertNeverPrompted();
    }
}
