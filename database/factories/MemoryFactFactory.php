<?php

namespace Database\Factories;

use App\Models\MemoryFact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryFact>
 */
class MemoryFactFactory extends Factory
{
    protected $model = MemoryFact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->sentence(),
            // Unlike TransactionFactory, no null default: a memory fact is
            // never left unindexed (see the migration), so a factory
            // instance without an explicit embedding still needs one.
            'embedding' => [fake()->randomFloat(2, -1, 1), fake()->randomFloat(2, -1, 1)],
        ];
    }
}
