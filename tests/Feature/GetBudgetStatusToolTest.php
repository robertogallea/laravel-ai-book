<?php

namespace Tests\Feature;

use App\Ai\Tools\GetBudgetStatusTool;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetBudgetStatusToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_status_reports_spending_only_for_the_requested_category(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00, 'entertainment' => 100.00]]);

        Transaction::factory()->create(['category' => 'groceries', 'amount' => 64.35, 'occurred_at' => now()]);
        Transaction::factory()->create(['category' => 'groceries', 'amount' => 22.10, 'occurred_at' => now()]);
        Transaction::factory()->create(['category' => 'entertainment', 'amount' => 300.00, 'occurred_at' => now()]);

        $result = (string) (new GetBudgetStatusTool)->handle(new Request(['category' => 'groceries']));

        $this->assertSame(
            'Category "groceries": 86.45 dollars spent so far this month, out of a 400.00 dollars monthly budget.',
            $result,
        );
    }

    public function test_spending_from_previous_months_is_not_counted_toward_the_current_month(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        Transaction::factory()->create(['category' => 'groceries', 'amount' => 64.35, 'occurred_at' => now()]);
        Transaction::factory()->create(['category' => 'groceries', 'amount' => 999.00, 'occurred_at' => now()->subMonthNoOverflow()]);
        Transaction::factory()->create(['category' => 'groceries', 'amount' => 999.00, 'occurred_at' => now()->subYearNoOverflow()]);

        $result = (string) (new GetBudgetStatusTool)->handle(new Request(['category' => 'groceries']));

        $this->assertSame(
            'Category "groceries": 64.35 dollars spent so far this month, out of a 400.00 dollars monthly budget.',
            $result,
        );
    }

    public function test_a_category_with_no_configured_budget_is_reported_explicitly(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        $result = (string) (new GetBudgetStatusTool)->handle(new Request(['category' => 'other']));

        $this->assertSame('No budget is set for the "other" category.', $result);
    }
}
