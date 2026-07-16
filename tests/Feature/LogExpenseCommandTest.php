<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use Tests\TestCase;

class LogExpenseCommandTest extends TestCase
{
    public function test_it_extracts_a_valid_expense_on_the_first_attempt(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 42.50, 'category' => 'restaurants', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 42.50 dollars at a restaurant on 2026-07-16'])
            ->expectsOutputToContain('Amount: 42.5')
            ->expectsOutputToContain('Category: restaurants')
            ->expectsOutputToContain('Date: 2026-07-16')
            ->assertExitCode(0);

        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->contains('I spent 42.50 dollars'));
    }

    public function test_it_retries_once_and_recovers_from_a_missing_field(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 42.50, 'category' => 'restaurants'],
            ['amount' => 42.50, 'category' => 'restaurants', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 42.50 dollars at a restaurant, no date given'])
            ->expectsOutputToContain('Date: 2026-07-16')
            ->assertExitCode(0);

        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->contains('date is missing'));
    }

    public function test_it_falls_back_after_exhausting_every_attempt(): void
    {
        $invalid = ['amount' => 42.50, 'category' => 'restaurants'];

        ExpenseExtractor::fake([$invalid, $invalid, $invalid]);

        $this->artisan('assistant:log-expense', ['description' => 'I spent 42.50 dollars at a restaurant, no date given'])
            ->expectsOutputToContain('after 3 attempts')
            ->assertExitCode(1);
    }
}
