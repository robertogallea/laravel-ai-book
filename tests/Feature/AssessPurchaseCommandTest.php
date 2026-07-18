<?php

namespace Tests\Feature;

use App\Ai\Agents\PurchaseAdvisor;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class AssessPurchaseCommandTest extends TestCase
{
    private const GOAL = 'Can the user afford to spend 600.00 dollars on a new laptop?';

    public function test_an_affordable_purchase_is_reported_with_no_suggested_action(): void
    {
        PurchaseAdvisor::fake([
            [
                'affordable' => true,
                'reasoning' => 'The current balance comfortably covers this purchase with budget to spare.',
                'suggested_action' => null,
            ],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Affordable: yes.')
            ->expectsOutputToContain('comfortably covers this purchase')
            ->assertExitCode(0);

        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->prompt === self::GOAL);
    }

    public function test_an_unaffordable_purchase_can_suggest_cancelling_an_unused_subscription_pending_approval(): void
    {
        Log::spy();

        PurchaseAdvisor::fake([
            [
                'affordable' => false,
                'reasoning' => 'The purchase would exceed the electronics budget unless the unused '
                    .'"Streaming Plus" subscription is cancelled first.',
                'suggested_action' => [
                    'subscription_name' => 'Streaming Plus',
                    'monthly_cost' => 12.99,
                    'days_unused' => 97,
                ],
            ],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Affordable: no.')
            ->expectsOutputToContain('exceed the electronics budget')
            ->expectsOutputToContain('Proposed action: Cancel the "Streaming Plus" subscription to free up budget')
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
                    && $payload['context']['Days since last use'] === 97,
            ));
    }

    public function test_a_rejected_suggestion_cancels_nothing_and_is_recorded_instead_of_retried(): void
    {
        Log::spy();

        PurchaseAdvisor::fake([
            [
                'affordable' => false,
                'reasoning' => 'The purchase would exceed the electronics budget unless the unused '
                    .'"Streaming Plus" subscription is cancelled first.',
                'suggested_action' => [
                    'subscription_name' => 'Streaming Plus',
                    'monthly_cost' => 12.99,
                    'days_unused' => 97,
                ],
            ],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Action rejected. Nothing was executed.')
            ->doesntExpectOutputToContain('has been cancelled')
            ->assertExitCode(2);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $payload) => $payload['type'] === 'cancel_subscription',
            ));
    }

    public function test_an_incomplete_structured_response_is_reported_instead_of_trusted(): void
    {
        // The schema declares "affordable" and "reasoning" required, but
        // the package does not enforce that on the model's behalf: a
        // response missing "reasoning" must not be silently trusted.
        PurchaseAdvisor::fake([
            ['affordable' => true],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('The assistant returned an incomplete assessment')
            ->assertExitCode(1);
    }

    public function test_a_malformed_suggested_action_is_reported_instead_of_trusted(): void
    {
        // "monthly_cost" is missing: nothing downstream should assume a
        // suggested action always carries every field the schema declares.
        PurchaseAdvisor::fake([
            [
                'affordable' => false,
                'reasoning' => 'Cancelling an unused subscription would free up enough budget.',
                'suggested_action' => ['subscription_name' => 'Streaming Plus', 'days_unused' => 97],
            ],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('The assistant returned an incomplete assessment')
            ->assertExitCode(1);
    }

    public function test_a_non_numeric_amount_is_rejected_before_any_call_is_made(): void
    {
        PurchaseAdvisor::fake();

        $this->artisan('assistant:assess-purchase', [
            'amount' => 'abc',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Amount must be a positive number, got "abc"')
            ->assertExitCode(2);

        PurchaseAdvisor::assertNeverPrompted();
    }

    public function test_a_non_positive_amount_is_rejected_before_any_call_is_made(): void
    {
        PurchaseAdvisor::fake();

        $this->artisan('assistant:assess-purchase', [
            'amount' => '0',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Amount must be a positive number, got "0"')
            ->assertExitCode(2);

        PurchaseAdvisor::assertNeverPrompted();
    }
}
