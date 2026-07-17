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
            ->expectsOutputToContain('Monthly cost: 12.99 dollars')
            ->expectsOutputToContain('Days since last use: 97')
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('Subscription "Streaming Plus" has been cancelled.')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.approved', Mockery::on(
                fn (array $payload) => $payload['type'] === 'cancel_subscription'
                    && $payload['context']['Monthly cost'] === '12.99 dollars'
                    && $payload['context']['Days since last use'] === 97
                    && $payload['detail'] === 'Subscription "Streaming Plus" has been cancelled.',
            ));
    }

    public function test_a_rejected_proposal_is_not_executed_and_is_recorded_instead_of_retried(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', self::ARGUMENTS)
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Action rejected. Nothing was executed.')
            ->doesntExpectOutputToContain('has been cancelled')
            ->assertExitCode(2);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $payload) => $payload['type'] === 'cancel_subscription'
                    && $payload['context']['Monthly cost'] === '12.99 dollars',
            ));
    }

    public function test_a_non_numeric_monthly_cost_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', array_merge(self::ARGUMENTS, ['monthly_cost' => 'abc']))
            ->expectsOutputToContain('Monthly cost must be a non-negative number, got "abc"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_negative_monthly_cost_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', array_merge(self::ARGUMENTS, ['monthly_cost' => '-5']))
            ->expectsOutputToContain('Monthly cost must be a non-negative number, got "-5"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_non_integer_days_unused_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:cancel-subscription', array_merge(self::ARGUMENTS, ['days_unused' => '3.5']))
            ->expectsOutputToContain('Days unused must be a non-negative whole number, got "3.5"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }
}
