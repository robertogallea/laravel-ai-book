<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use Tests\TestCase;

/**
 * At this point in the book, no eval set exists for the categorization
 * prompt (Chapter 3): the only check LogExpenseCommand runs is its own
 * structural validation, which only confirms the category is one of the
 * known enum values, never whether it is the right one for the expense
 * described.
 *
 * This stands in for a prompt change that looks like a pure improvement,
 * fixing an ambiguous case that used to fall back to "other", while
 * silently breaking a rarer case that genuinely belongs in "other". Both
 * fake responses below are what that hypothetical modified prompt would
 * return; both sail through unnoticed, because nothing here checks for
 * anything beyond a structurally valid category.
 */
class CategorizationPromptChangeWithoutEvalsTest extends TestCase
{
    public function test_the_fixed_ambiguous_case_passes(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 12.40, 'category' => 'groceries', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'Charge from Corner Market, which sells both groceries and coffee to go',
        ])
            ->expectsOutputToContain('Category: groceries')
            ->assertExitCode(0);
    }

    public function test_the_regressed_rare_case_also_passes_because_nothing_checks_it(): void
    {
        // This expense genuinely belongs in "other": no more specific
        // category fits a one-time fee not tied to any particular service.
        // The simulated prompt change forces it into "entertainment"
        // anyway, chasing the same preference for specificity that fixed
        // the case above. "entertainment" is still a valid enum value, so
        // LogExpenseCommand's validation has nothing to object to: the
        // regression is real, but invisible to every check that exists at
        // this point in the book.
        ExpenseExtractor::fake([
            ['amount' => 15.00, 'category' => 'entertainment', 'date' => '2026-07-16'],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'One-time miscellaneous membership renewal fee, not tied to any specific service',
        ])
            ->expectsOutputToContain('Category: entertainment')
            ->assertExitCode(0);
    }
}
