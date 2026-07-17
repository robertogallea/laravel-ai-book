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
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('200.00 dollars have been moved from checking to savings.')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.approved', Mockery::on(
                fn (array $context) => $context['type'] === 'transfer_funds',
            ));
    }

    public function test_a_rejected_transfer_is_not_executed_and_is_recorded_instead_of_retried(): void
    {
        Log::spy();

        $this->artisan('assistant:transfer-funds', self::ARGUMENTS)
            ->expectsConfirmation('Approve this action?', 'no')
            ->expectsOutputToContain('Action rejected. Nothing was executed.')
            ->doesntExpectOutputToContain('have been moved')
            ->assertExitCode(1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.rejected', Mockery::on(
                fn (array $context) => $context['type'] === 'transfer_funds',
            ));
    }
}
