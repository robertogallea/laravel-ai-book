<?php

namespace Tests\Feature;

use App\Ai\Agents\MonthlyReportSummarizer;
use App\Ai\Agents\OverspendingAdvisor;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'user@example.com']);
    }

    public function test_every_configured_category_reaches_the_summary_even_with_no_transactions(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00, 'entertainment' => 100.00]]);

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 64.35, 'occurred_at' => now()]);
        // No "entertainment" transaction at all this month: the category
        // still has to reach the summarizer, at zero dollars spent,
        // unlike the agentic version where an uncalled category simply
        // never appears in the report at all.

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries and entertainment are both within budget.'],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
            ->expectsOutputToContain('Groceries and entertainment are both within budget.')
            ->assertExitCode(0);

        MonthlyReportSummarizer::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'groceries: 64.35 of 400.00 dollars spent')
                && str_contains($prompt->prompt, 'entertainment: 0.00 of 100.00 dollars spent')
        );

        OverspendingAdvisor::assertNeverPrompted();
    }

    public function test_another_users_transactions_do_not_count_toward_this_report(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        $otherUser = User::factory()->create();

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 64.35, 'occurred_at' => now()]);
        Transaction::factory()->create(['user_id' => $otherUser->id, 'category' => 'groceries', 'amount' => 999.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries is within budget this month.'],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
            ->assertExitCode(0);

        MonthlyReportSummarizer::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'groceries: 64.35 of 400.00 dollars spent')
        );
    }

    public function test_recommendations_are_skipped_when_no_category_is_over_budget(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 100.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries spending is well within budget this month.'],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
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
        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 440.00, 'occurred_at' => now()]);
        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'entertainment', 'amount' => 150.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries and entertainment are both over budget this month.'],
        ]);

        OverspendingAdvisor::fake([
            ['recommendations' => 'Consider pausing discretionary entertainment purchases until next month.'],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
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

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
            ->expectsOutputToContain('The assistant returned an incomplete summary')
            ->assertExitCode(1);

        OverspendingAdvisor::assertNeverPrompted();
    }

    public function test_an_incomplete_recommendations_response_is_reported_instead_of_trusted(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 500.00, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries is over budget this month.'],
        ]);

        OverspendingAdvisor::fake([
            [],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
            ->expectsOutputToContain('Groceries is over budget this month.')
            ->expectsOutputToContain('The assistant returned incomplete recommendations')
            ->assertExitCode(1);
    }

    public function test_a_zero_budget_category_with_any_spending_is_treated_as_over_budget(): void
    {
        config(['finance.budgets' => ['groceries' => 0.00]]);

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 0.01, 'occurred_at' => now()]);

        MonthlyReportSummarizer::fake([
            ['summary' => 'Groceries has no budget allocated this month.'],
        ]);

        OverspendingAdvisor::fake([
            ['recommendations' => 'Set a budget for groceries or stop spending in this category.'],
        ]);

        $this->artisan('assistant:generate-monthly-report', ['--user' => $this->user->email])
            ->expectsOutputToContain('Set a budget for groceries or stop spending in this category.')
            ->assertExitCode(0);

        OverspendingAdvisor::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'groceries: INF% over budget')
        );
    }

    public function test_the_user_option_is_required(): void
    {
        MonthlyReportSummarizer::fake();

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('The --user option is required')
            ->assertExitCode(2);

        MonthlyReportSummarizer::assertNeverPrompted();
    }
}
