<?php

namespace App\Ai\Tools;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Takes no parameters: unlike GetBudgetStatusTool, the balance is not
 * scoped by anything the model could usefully choose.
 */
class GetAccountBalanceTool implements Tool
{
    public function description(): Stringable|string
    {
        return "Get the user's current account balance.";
    }

    public function handle(Request $request): Stringable|string
    {
        $balance = config('finance.starting_balance') - Transaction::sum('amount');

        return sprintf('Current account balance: %.2f dollars.', $balance);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
