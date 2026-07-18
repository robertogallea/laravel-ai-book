<?php

namespace Tests\Feature;

use App\Ai\Tools\GetAccountBalanceTool;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetAccountBalanceToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_balance_is_the_starting_balance_minus_every_recorded_transaction(): void
    {
        config(['finance.starting_balance' => 1200.00]);

        Transaction::factory()->create(['amount' => 38.50]);
        Transaction::factory()->create(['amount' => 64.35]);

        $result = (new GetAccountBalanceTool)->handle(new Request);

        $this->assertSame('Current account balance: 1097.15 dollars.', (string) $result);
    }

    public function test_the_balance_is_the_full_starting_balance_when_nothing_has_been_recorded_yet(): void
    {
        config(['finance.starting_balance' => 1200.00]);

        $result = (new GetAccountBalanceTool)->handle(new Request);

        $this->assertSame('Current account balance: 1200.00 dollars.', (string) $result);
    }
}
