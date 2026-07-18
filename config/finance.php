<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Starting Balance
    |--------------------------------------------------------------------------
    |
    | A fictitious starting balance for the example app's single user. The
    | balance App\Ai\Tools\GetAccountBalanceTool reports is this figure
    | minus every recorded transaction, not a persisted running total kept
    | in its own right.
    |
    */

    'starting_balance' => 1200.00,

    /*
    |--------------------------------------------------------------------------
    | Recurring Subscriptions
    |--------------------------------------------------------------------------
    |
    | Fictitious recurring monthly subscriptions, standing in for whatever
    | an account-aggregation feature would otherwise supply. "days_unused"
    | is what App\Ai\Agents\PurchaseAdvisor weighs when deciding whether
    | cancelling one is a reasonable way to free up budget.
    |
    */

    'subscriptions' => [
        ['name' => 'Streaming Plus', 'monthly_cost' => 12.99, 'days_unused' => 97],
        ['name' => 'Cloud Backup', 'monthly_cost' => 6.99, 'days_unused' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monthly Budgets
    |--------------------------------------------------------------------------
    |
    | A monthly spending limit per category. Keys match
    | App\Ai\Agents\ExpenseExtractor::CATEGORIES.
    |
    */

    'budgets' => [
        'groceries' => 400.00,
        'restaurants' => 150.00,
        'transportation' => 120.00,
        'entertainment' => 100.00,
        'utilities' => 200.00,
        'other' => 100.00,
    ],

];
