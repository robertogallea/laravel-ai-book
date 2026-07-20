<?php

namespace Database\Factories;

use App\Models\EvalFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvalFeedback>
 */
class EvalFeedbackFactory extends Factory
{
    protected $model = EvalFeedback::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input' => fake()->sentence(),
            'category' => fake()->randomElement([
                'groceries', 'restaurants', 'transportation', 'entertainment', 'utilities', 'other',
            ]),
            'status' => 'pending_review',
            'expected_category' => null,
        ];
    }
}
