<?php

namespace Tests\Feature;

use App\Ai\Agents\SubscriptionCancellationAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Mockery;
use Tests\TestCase;

class RequestSubscriptionCancellationCommandTest extends TestCase
{
    use RefreshDatabase;

    private const INSTRUCTION = 'Cancel my Streaming Plus subscription';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'user@example.com']);

        // A faked agent still goes through the real conversation-persistence
        // middleware, which by default asks the (also faked) provider for a
        // short title on the very first turn: without disabling that here,
        // it would silently consume the next queued fake response instead of
        // the assertions below.
        config(['ai.conversations.generate_title' => false]);
    }

    public function test_an_approved_cancellation_resumes_the_conversation_and_prints_the_final_reply(): void
    {
        // Agent::fake() replaces the whole tool-calling loop with canned
        // responses, so it never actually invokes CancelSubscriptionTool's
        // handle(): the "approved" audit-log entry that method writes is
        // only exercised by the real, interactive end-to-end run, not by
        // this test.
        SubscriptionCancellationAssistant::fake([
            AgentResponse::fakeWithPendingApprovals([
                new PendingApproval(
                    id: 'call_abc',
                    tool: 'cancel_subscription',
                    arguments: ['subscription_name' => 'Streaming Plus'],
                    reason: 'Cancelling a subscription is an irreversible financial action.',
                ),
            ]),
            'Subscription "Streaming Plus" has been cancelled.',
        ]);

        $this->artisan('assistant:request-subscription-cancellation', [
            'instruction' => self::INSTRUCTION,
            '--user' => $this->user->email,
        ])
            ->expectsOutputToContain('Proposed action: cancel_subscription')
            ->expectsOutputToContain('Reason: Cancelling a subscription is an irreversible financial action.')
            ->expectsOutputToContain('subscription_name: Streaming Plus')
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('Subscription "Streaming Plus" has been cancelled.')
            ->assertExitCode(0);
    }

    public function test_a_rejected_cancellation_is_logged_and_never_reaches_the_tool(): void
    {
        Log::spy();

        SubscriptionCancellationAssistant::fake([
            AgentResponse::fakeWithPendingApprovals([
                new PendingApproval(
                    id: 'call_abc',
                    tool: 'cancel_subscription',
                    arguments: ['subscription_name' => 'Streaming Plus'],
                    reason: 'Cancelling a subscription is an irreversible financial action.',
                ),
            ]),
            'Understood, the subscription was not cancelled.',
        ]);

        $this->artisan('assistant:request-subscription-cancellation', [
            'instruction' => self::INSTRUCTION,
            '--user' => $this->user->email,
        ])
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Understood, the subscription was not cancelled.')
            ->doesntExpectOutputToContain('has been cancelled.')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $payload) => $payload['type'] === 'cancel_subscription'
                    && $payload['context']['subscription_name'] === 'Streaming Plus',
            ));
    }

    public function test_the_user_option_is_required(): void
    {
        SubscriptionCancellationAssistant::fake();

        $this->artisan('assistant:request-subscription-cancellation', [
            'instruction' => self::INSTRUCTION,
        ])
            ->expectsOutputToContain('The --user option is required')
            ->assertExitCode(2);

        SubscriptionCancellationAssistant::assertNeverPrompted();
    }
}
