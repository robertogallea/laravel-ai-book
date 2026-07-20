<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use Tests\TestCase;

/**
 * The categorization eval set built in the previous increment is a fixed
 * array of five cases, written once and never revisited. This stands in
 * for a real miscategorization that only surfaces once the assistant is
 * actually used: a refund description the assistant categorizes as
 * "other" when it clearly belongs in "restaurants". Nothing about this
 * case exists in the eval set, so running it proves nothing about whether
 * this specific mistake would ever be caught: the eval set stays green
 * while a real problem goes unnoticed right next to it.
 */
class StaticEvalDatasetMissesRealCaseTest extends TestCase
{
    public function test_a_real_miscategorized_case_from_usage_goes_unnoticed_by_the_static_eval_set(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 8.50, 'category' => 'other', 'date' => '2026-07-20'],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => "Refund adjustment for an earlier restaurant charge at Luigi's Trattoria",
        ])
            ->expectsOutputToContain('Category: other')
            ->assertExitCode(0);

        // The eval set has no case for this description at all: it was
        // written before this mistake was ever observed, and nothing
        // updates it automatically.
        ExpenseExtractor::fake([
            ['amount' => 24.90, 'category' => 'restaurants', 'date' => '2026-07-16'],
            ['amount' => 55.00, 'category' => 'transportation', 'date' => '2026-07-16'],
            ['amount' => 78.30, 'category' => 'utilities', 'date' => '2026-07-16'],
            ['amount' => 12.40, 'category' => 'groceries', 'date' => '2026-07-16'],
            ['amount' => 8.00, 'category' => 'other', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:eval-categorization')
            ->expectsOutputToContain('Categorization eval: 5/5 cases passed.')
            ->assertExitCode(0);
    }
}
