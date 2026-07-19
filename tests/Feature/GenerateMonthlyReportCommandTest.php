<?php

namespace Tests\Feature;

use App\Ai\Agents\MonthlyReportAdvisor;
use Tests\TestCase;

class GenerateMonthlyReportCommandTest extends TestCase
{
    public function test_the_reported_summary_can_cover_only_a_few_categories(): void
    {
        // config('finance.budgets') tracks six categories, but nothing here
        // checks how many of them the assistant actually looked at before
        // concluding: a summary covering only two of the six is accepted
        // exactly like one covering all of them.
        MonthlyReportAdvisor::fake([
            [
                'summary' => 'Groceries are within budget. Transportation is slightly over.',
            ],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('Groceries are within budget. Transportation is slightly over.')
            ->assertExitCode(0);
    }

    public function test_the_reported_summary_can_cover_a_different_set_of_categories_on_another_run(): void
    {
        // Same command, same six configured categories, a different
        // plausible summary: which categories get checked, and how many
        // tool calls that costs, is decided anew by the model every time,
        // not fixed by the application.
        MonthlyReportAdvisor::fake([
            [
                'summary' => 'Utilities and entertainment spending are both on track this month.',
            ],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('Utilities and entertainment spending are both on track this month.')
            ->assertExitCode(0);
    }

    public function test_an_incomplete_structured_response_is_reported_instead_of_trusted(): void
    {
        MonthlyReportAdvisor::fake([
            [],
        ]);

        $this->artisan('assistant:generate-monthly-report')
            ->expectsOutputToContain('The assistant returned an incomplete report')
            ->assertExitCode(1);
    }
}
