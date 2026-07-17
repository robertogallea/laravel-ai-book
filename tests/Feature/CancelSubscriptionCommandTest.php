<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CancelSubscriptionCommandTest extends TestCase
{
    private const ARGUMENTS = [
        'name' => 'Streaming Plus',
        'monthly_cost' => '12.99',
        'days_unused' => '97',
    ];

    public function test_the_subscription_is_cancelled_only_after_explicit_approval(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', self::ARGUMENTS)
            ->expectsOutputToContain('Proposed action: Cancel the "Streaming Plus" subscription')
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('Subscription "Streaming Plus" has been cancelled.')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.approved', Mockery::on(
                fn (array $context) => $context['type'] === 'cancel_subscription',
            ));
    }

    public function test_a_rejected_proposal_is_not_executed_and_is_recorded_instead_of_retried(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', self::ARGUMENTS)
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Action rejected. Nothing was executed.')
            ->doesntExpectOutputToContain('has been cancelled')
            ->assertExitCode(1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $context) => $context['type'] === 'cancel_subscription',
            ));
    }
}
