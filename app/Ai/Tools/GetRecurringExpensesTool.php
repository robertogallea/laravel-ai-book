<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetRecurringExpensesTool implements Tool
{
    public function description(): Stringable|string
    {
        return "List the user's recurring monthly subscriptions, with their monthly cost and days since last used.";
    }

    public function handle(Request $request): Stringable|string
    {
        $subscriptions = new Collection(config('finance.subscriptions'));

        if ($subscriptions->isEmpty()) {
            return 'No recurring subscriptions on file.';
        }

        return $subscriptions
            ->map(fn (array $subscription) => sprintf(
                '- %s: %.2f dollars/month, last used %d days ago',
                $subscription['name'],
                $subscription['monthly_cost'],
                $subscription['days_unused'],
            ))
            ->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
