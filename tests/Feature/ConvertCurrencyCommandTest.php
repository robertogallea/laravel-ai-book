<?php

namespace Tests\Feature;

use App\Ai\Agents\CurrencyAdvisor;
use Tests\TestCase;

class ConvertCurrencyCommandTest extends TestCase
{
    public function test_it_prints_the_assistants_conversion(): void
    {
        CurrencyAdvisor::fake([
            '500.00 dollars is about 460.00 euros, at a rate of 1 USD = 0.92 EUR.',
        ]);

        $this->artisan('assistant:convert-currency', [
            'question' => 'How much are 500 dollars in euros?',
        ])
            ->expectsOutputToContain('500.00 dollars is about 460.00 euros')
            ->assertExitCode(0);

        CurrencyAdvisor::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'How much are 500 dollars in euros?'
        );
    }
}
