<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use App\Console\Commands\Concerns\DisclosesAiInteraction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:ask {question : The question to ask the assistant}')]
#[Description('Send a single, one-off question to the finance assistant (no conversation history)')]
class AskCommand extends Command
{
    use DisclosesAiInteraction;

    /**
     * Execute the console command.
     *
     * This is the simplest possible call to the assistant: a single
     * question, a single synchronous response, no history involved. It is
     * the reference example the rest of the book builds on top of.
     */
    public function handle(): void
    {
        $this->discloseAiInteraction();

        $response = (new FinanceAssistant)->prompt($this->argument('question'));

        $this->line($response->text);
    }
}
