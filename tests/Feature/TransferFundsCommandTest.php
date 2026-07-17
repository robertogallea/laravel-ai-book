<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransferFundsCommandTest extends TestCase
{
    public function test_it_moves_the_funds_immediately_with_no_confirmation(): void
    {
        $this->artisan('assistant:transfer-funds', [
            'from' => 'checking',
            'to' => 'savings',
            'amount' => '200',
        ])
            ->expectsOutputToContain('200.00 dollars moved from checking to savings.')
            ->assertExitCode(0);
    }
}
