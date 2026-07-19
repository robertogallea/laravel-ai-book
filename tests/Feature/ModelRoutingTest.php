<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\PurchaseAdvisor;
use Tests\TestCase;

class ModelRoutingTest extends TestCase
{
    public function test_categorization_and_planning_currently_resolve_to_the_exact_same_model(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 12.50, 'category' => 'groceries', 'date' => '2026-07-19'],
        ]);

        PurchaseAdvisor::fake([
            ['affordable' => true, 'reasoning' => 'Well within budget.', 'suggested_action' => null],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'Coffee, 12.50 dollars, today.',
        ])->assertExitCode(0);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])->assertExitCode(0);

        // Neither agent declares which model it wants: both fall through to
        // the same provider default, whether the task is a closed-set
        // classification or a multi-step planning loop.
        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->model === 'gpt-5.4');
        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->model === 'gpt-5.4');
    }
}
