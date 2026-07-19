<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use Tests\TestCase;

class AskCommandMemoryGapTest extends TestCase
{
    public function test_a_goal_declared_in_one_session_is_unknown_in_the_next(): void
    {
        FinanceAssistant::fake([
            "Got it, I'll keep that in mind for our conversation.",
            "I don't have any information about a savings goal for you.",
        ]);

        // First session: the user declares a goal. assistant:ask has no
        // history and nothing to persist it with, so nothing about this
        // exchange outlives this single command invocation.
        $this->artisan('assistant:ask', [
            'question' => 'I want to save 200 dollars a month for vacation.',
        ])->assertExitCode(0);

        // Second session: a brand new invocation of the same one-shot
        // command, carrying nothing over from the first. The prompt that
        // actually reaches the assistant is the bare question below, with
        // no trace of the goal declared a moment ago.
        $this->artisan('assistant:ask', [
            'question' => 'What is my savings goal?',
        ])
            ->expectsOutputToContain("I don't have any information about a savings goal")
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'What is my savings goal?'
        );
    }
}
