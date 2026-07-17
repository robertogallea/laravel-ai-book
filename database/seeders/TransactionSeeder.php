<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TransactionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a small, fictitious transaction history: enough to observe a
     * retrieval that actually has to choose among categories, not just
     * confirm the only row available.
     */
    public function run(): void
    {
        $today = Carbon::parse('2026-07-17');

        $transactions = [
            ['merchant' => "Mario's Diner", 'category' => 'restaurants', 'amount' => 38.50, 'daysAgo' => 2],
            ['merchant' => 'Sushi Corner', 'category' => 'restaurants', 'amount' => 27.90, 'daysAgo' => 6],
            ['merchant' => 'Burger Yard', 'category' => 'restaurants', 'amount' => 14.20, 'daysAgo' => 11],
            ['merchant' => 'SuperMart', 'category' => 'groceries', 'amount' => 64.35, 'daysAgo' => 3],
            ['merchant' => 'Farmers Market', 'category' => 'groceries', 'amount' => 22.10, 'daysAgo' => 9],
            ['merchant' => 'City Metro', 'category' => 'transportation', 'amount' => 45.00, 'daysAgo' => 1],
            ['merchant' => 'RideShare Co', 'category' => 'transportation', 'amount' => 18.75, 'daysAgo' => 8],
            ['merchant' => 'Electric Utility Co', 'category' => 'utilities', 'amount' => 92.40, 'daysAgo' => 14],
        ];

        foreach ($transactions as $transaction) {
            Transaction::create([
                'merchant' => $transaction['merchant'],
                'category' => $transaction['category'],
                'amount' => $transaction['amount'],
                'occurred_at' => $today->copy()->subDays($transaction['daysAgo']),
            ]);
        }
    }
}
