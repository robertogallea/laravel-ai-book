<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use Tests\TestCase;

class EvalCategorizationCommandTest extends TestCase
{
    public function test_the_full_eval_set_passes_against_a_correctly_behaving_prompt(): void
    {
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

    public function test_a_prompt_change_that_regresses_a_rare_case_is_caught_before_release(): void
    {
        // Same simulated prompt change as
        // CategorizationPromptChangeWithoutEvalsTest: it fixes the
        // ambiguous case, but forces the genuinely miscellaneous one into
        // "entertainment" instead of "other". Unlike LogExpenseCommand's
        // own validation, this eval set was built to include exactly that
        // rare case, so this time the regression does not go unnoticed.
        ExpenseExtractor::fake([
            ['amount' => 24.90, 'category' => 'restaurants', 'date' => '2026-07-16'],
            ['amount' => 55.00, 'category' => 'transportation', 'date' => '2026-07-16'],
            ['amount' => 78.30, 'category' => 'utilities', 'date' => '2026-07-16'],
            ['amount' => 12.40, 'category' => 'groceries', 'date' => '2026-07-16'],
            ['amount' => 15.00, 'category' => 'entertainment', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:eval-categorization')
            ->expectsOutputToContain('Categorization eval: 4/5 cases passed.')
            ->expectsOutputToContain('Failed cases: genuinely_miscellaneous_fee')
            ->assertExitCode(1);
    }
}
