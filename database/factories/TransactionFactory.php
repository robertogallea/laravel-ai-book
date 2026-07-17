<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant' => fake()->company(),
            'category' => fake()->randomElement(['restaurants', 'groceries', 'transportation', 'utilities']),
            'amount' => fake()->randomFloat(2, 5, 150),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'embedding' => null,
        ];
    }
}
