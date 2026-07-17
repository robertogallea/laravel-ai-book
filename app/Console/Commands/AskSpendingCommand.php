<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:ask-spending {question : A question about the user\'s spending history}')]
#[Description('Ask the assistant a question about the user\'s spending history')]
class AskSpendingCommand extends Command
{
    /**
     * Execute the console command.
     *
     * The assistant answers using only its own general knowledge: it never
     * sees the user's actual transactions, so a question that depends on
     * them can only be met with a generic answer or a request for figures
     * it does not have, never with the real numbers.
     */
    public function handle(): void
    {
        $response = (new FinanceAssistant)->prompt($this->argument('question'));

        $this->line($response->text);
    }
}
