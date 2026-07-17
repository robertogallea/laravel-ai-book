<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TransferFundsCommandTest extends TestCase
{
    private const ARGUMENTS = [
        'from' => 'checking',
        'to' => 'savings',
        'amount' => '200',
    ];

    public function test_the_funds_are_moved_only_after_explicit_approval(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', self::ARGUMENTS)
            ->expectsOutputToContain('Proposed action: Move 200.00 dollars from checking to savings')
            ->expectsOutputToContain('From account: checking')
            ->expectsOutputToContain('To account: savings')
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('200.00 dollars have been moved from checking to savings.')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.approved', Mockery::on(
                fn (array $payload) => $payload['type'] === 'transfer_funds'
                    && $payload['context']['From account'] === 'checking'
                    && $payload['context']['To account'] === 'savings'
                    && $payload['detail'] === '200.00 dollars have been moved from checking to savings.',
            ));
    }

    public function test_a_rejected_transfer_is_not_executed_and_is_recorded_instead_of_retried(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', self::ARGUMENTS)
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Action rejected. Nothing was executed.')
            ->doesntExpectOutputToContain('have been moved')
            ->assertExitCode(2);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $payload) => $payload['type'] === 'transfer_funds'
                    && $payload['context']['Amount'] === '200.00 dollars',
            ));
    }

    public function test_a_non_numeric_amount_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', array_merge(self::ARGUMENTS, ['amount' => 'abc']))
            ->expectsOutputToContain('Amount must be a positive number, got "abc"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_negative_amount_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', array_merge(self::ARGUMENTS, ['amount' => '-50']))
            ->expectsOutputToContain('Amount must be a positive number, got "-50"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_zero_amount_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', array_merge(self::ARGUMENTS, ['amount' => '0']))
            ->expectsOutputToContain('Amount must be a positive number, got "0"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }

    public function test_transferring_between_the_same_account_is_rejected_before_any_proposal_is_made(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', array_merge(self::ARGUMENTS, ['from' => 'checking', 'to' => 'checking']))
            ->expectsOutputToContain('Source and destination accounts must be different, both were "checking"')
            ->assertExitCode(2);

        Log::shouldNotHaveReceived('info');
    }
}
