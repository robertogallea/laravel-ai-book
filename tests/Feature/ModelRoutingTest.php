<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\PurchaseAdvisor;
use Tests\TestCase;

class ModelRoutingTest extends TestCase
{
    public function test_categorization_is_routed_to_the_cheapest_available_model(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 12.50, 'category' => 'groceries', 'date' => '2026-07-19'],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'Coffee, 12.50 dollars, today.',
        ])->assertExitCode(0);

        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->model === 'gpt-5.4-nano');
    }

    public function test_planning_is_routed_to_the_smartest_available_model(): void
    {
        PurchaseAdvisor::fake([
            ['affordable' => true, 'reasoning' => 'Well within budget.', 'suggested_action' => null],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])->assertExitCode(0);

        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->model === 'gpt-5.4-pro');
    }

    public function test_categorization_and_planning_no_longer_resolve_to_the_same_model(): void
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

        // Neither call site chose a model itself: the routing lives once,
        // on each agent class, and every caller inherits it for free.
        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->model !== 'gpt-5.4');
        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->model !== 'gpt-5.4');
    }
}
