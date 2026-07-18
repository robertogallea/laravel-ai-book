<?php

namespace Tests\Feature;

use App\Ai\Tools\GetRecurringExpensesTool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetRecurringExpensesToolTest extends TestCase
{
    public function test_every_configured_subscription_is_listed_with_its_cost_and_days_unused(): void
    {
        config(['finance.subscriptions' => [
            ['name' => 'Streaming Plus', 'monthly_cost' => 12.99, 'days_unused' => 97],
            ['name' => 'Cloud Backup', 'monthly_cost' => 6.99, 'days_unused' => 4],
        ]]);

        $result = (string) (new GetRecurringExpensesTool)->handle(new Request);

        $this->assertSame(
            "- Streaming Plus: 12.99 dollars/month, last used 97 days ago\n"
            .'- Cloud Backup: 6.99 dollars/month, last used 4 days ago',
            $result,
        );
    }

    public function test_an_empty_subscription_list_is_reported_explicitly(): void
    {
        config(['finance.subscriptions' => []]);

        $result = (string) (new GetRecurringExpensesTool)->handle(new Request);

        $this->assertSame('No recurring subscriptions on file.', $result);
    }
}
