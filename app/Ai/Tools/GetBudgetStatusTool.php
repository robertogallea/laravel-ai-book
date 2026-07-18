<?php

namespace App\Ai\Tools;

use App\Ai\Agents\ExpenseExtractor;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBudgetStatusTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get the monthly budget limit and amount already spent so far for a specific spending category.';
    }

    public function handle(Request $request): Stringable|string
    {
        $category = $request['category'];
        $limit = config("finance.budgets.{$category}");

        if ($limit === null) {
            return "No budget is set for the \"{$category}\" category.";
        }

        $spent = Transaction::where('category', $category)
            ->whereYear('occurred_at', now()->year)
            ->whereMonth('occurred_at', now()->month)
            ->sum('amount');

        return sprintf(
            'Category "%s": %.2f dollars spent so far this month, out of a %.2f dollars monthly budget.',
            $category,
            $spent,
            $limit,
        );
    }

    /**
     * The category enum matches ExpenseExtractor's, the same taxonomy used
     * everywhere else in the app a spending category is recorded or read.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->required()->enum(ExpenseExtractor::CATEGORIES),
        ];
    }
}
