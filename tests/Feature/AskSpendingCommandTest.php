<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use Tests\TestCase;

class AskSpendingCommandTest extends TestCase
{
    public function test_a_question_about_real_spending_gets_only_a_generic_answer_with_no_transaction_data(): void
    {
        FinanceAssistant::fake([
            "I don't have your transaction history in front of me, so I can't give you an exact figure. "
            .'As a general guideline, keeping restaurant spending under 10-15% of your monthly budget works well for most people.',
        ]);

        $this->artisan('assistant:ask-spending', [
            'question' => 'How much did I spend on restaurants this month?',
        ])
            ->expectsOutputToContain("I don't have your transaction history")
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->contains('How much did I spend on restaurants this month?')
        );
    }
}
