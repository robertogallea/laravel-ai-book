<?php

namespace Tests\Feature;

use Tests\TestCase;

class CancelSubscriptionCommandTest extends TestCase
{
    public function test_it_cancels_the_subscription_immediately_with_no_confirmation(): void
    {
        $this->artisan('assistant:cancel-subscription', [
            'name' => 'Streaming Plus',
            'monthly_cost' => '12.99',
            'days_unused' => '97',
        ])
            ->expectsOutputToContain('Subscription "Streaming Plus" has been cancelled.')
            ->assertExitCode(0);
    }
}
