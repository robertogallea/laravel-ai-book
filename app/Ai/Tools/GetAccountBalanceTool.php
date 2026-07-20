<?php

namespace App\Ai\Tools;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Takes no schema parameters: unlike GetBudgetStatusTool, the balance is
 * not scoped by anything the model could usefully choose. It is still
 * scoped, by the user given at construction time: this tool cannot exist
 * without knowing whose balance it reports.
 */
class GetAccountBalanceTool implements Tool
{
    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return "Get the user's current account balance.";
    }

    public function handle(Request $request): Stringable|string
    {
        $balance = config('finance.starting_balance') - Transaction::query()->ownedBy($this->user)->sum('amount');

        return sprintf('Current account balance: %.2f dollars.', $balance);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
