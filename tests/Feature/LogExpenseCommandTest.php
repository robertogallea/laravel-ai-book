<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use Tests\TestCase;

class LogExpenseCommandTest extends TestCase
{
    public function test_it_extracts_a_cleanly_phrased_expense(): void
    {
        ExpenseExtractor::fake([
            'Amount: 42.50 dollars, Category: restaurants, Date: 2026-07-16',
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 42.50 dollars at a restaurant on 2026-07-16'])
            ->expectsOutputToContain('Amount: 42.5')
            ->expectsOutputToContain('Category: restaurants')
            ->expectsOutputToContain('Date: 2026-07-16')
            ->assertExitCode(0);
    }

    public function test_it_fails_to_parse_an_amount_spelled_out_in_words(): void
    {
        ExpenseExtractor::fake([
            'Amount: forty two dollars, Category: restaurants, Date: 2026-07-16',
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent forty two dollars at a restaurant'])
            ->expectsOutputToContain('Could not parse amount')
            ->assertExitCode(1);
    }

    public function test_it_fails_to_parse_a_category_not_in_its_keyword_list(): void
    {
        ExpenseExtractor::fake([
            'Amount: 12 dollars, Category: coffee shop, Date: 2026-07-16',
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 12 dollars at a coffee shop'])
            ->expectsOutputToContain('Could not parse category')
            ->assertExitCode(1);
    }

    public function test_it_fails_to_parse_a_relative_date(): void
    {
        ExpenseExtractor::fake([
            'Amount: 12 dollars, Category: groceries, Date: yesterday',
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 12 dollars on groceries yesterday'])
            ->expectsOutputToContain('Could not parse date')
            ->assertExitCode(1);
    }
}
