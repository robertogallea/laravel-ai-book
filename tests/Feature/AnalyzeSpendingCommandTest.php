<?php

namespace Tests\Feature;

use App\Ai\Agents\SpendingAnalyst;
use Laravel\Ai\Exceptions\AiException;
use Tests\TestCase;

class AnalyzeSpendingCommandTest extends TestCase
{
    public function test_it_prints_the_assistants_analysis(): void
    {
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 60.50, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->expectsOutputToContain('Your grocery spending looks stable so far.')
            ->assertExitCode(0);
    }

    public function test_a_wrong_total_is_shown_with_no_uncertainty_signal(): void
    {
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 999.99, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 999.99 dollars')
            ->doesntExpectOutputToContain('approximate')
            ->assertExitCode(0);
    }

    public function test_a_provider_failure_crashes_the_command_with_no_retry_or_fallback(): void
    {
        SpendingAnalyst::fake([
            fn () => throw new AiException('The AI service did not respond in time.'),
        ]);

        $this->expectException(AiException::class);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ]);
    }
}
