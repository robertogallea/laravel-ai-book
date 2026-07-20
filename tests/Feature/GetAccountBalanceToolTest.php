<?php

namespace Tests\Feature;

use App\Ai\Tools\GetAccountBalanceTool;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetAccountBalanceToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_balance_is_the_starting_balance_minus_every_recorded_transaction_owned_by_the_user(): void
    {
        config(['finance.starting_balance' => 1200.00]);

        $user = User::factory()->create();

        Transaction::factory()->create(['user_id' => $user->id, 'amount' => 38.50]);
        Transaction::factory()->create(['user_id' => $user->id, 'amount' => 64.35]);

        $result = (new GetAccountBalanceTool($user))->handle(new Request);

        $this->assertSame('Current account balance: 1097.15 dollars.', (string) $result);
    }

    public function test_the_balance_is_the_full_starting_balance_when_nothing_has_been_recorded_yet(): void
    {
        config(['finance.starting_balance' => 1200.00]);

        $user = User::factory()->create();

        $result = (new GetAccountBalanceTool($user))->handle(new Request);

        $this->assertSame('Current account balance: 1200.00 dollars.', (string) $result);
    }

    public function test_another_users_transactions_do_not_affect_the_reported_balance(): void
    {
        config(['finance.starting_balance' => 1200.00]);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Transaction::factory()->create(['user_id' => $user->id, 'amount' => 38.50]);
        Transaction::factory()->create(['user_id' => $otherUser->id, 'amount' => 900.00]);

        $result = (new GetAccountBalanceTool($user))->handle(new Request);

        $this->assertSame('Current account balance: 1161.50 dollars.', (string) $result);
    }
}
