<?php

namespace Database\Factories;

use App\Models\ReportRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportRequest>
 */
class ReportRequestFactory extends Factory
{
    protected $model = ReportRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now()->format('Y-m'),
            'status' => ReportRequest::STATUS_PENDING,
        ];
    }
}
