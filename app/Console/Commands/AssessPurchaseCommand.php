<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:assess-purchase {amount : Cost of the purchase being considered} {description : Short description of what is being purchased}')]
#[Description('Ask the assistant whether a purchase is affordable')]
class AssessPurchaseCommand extends Command
{
    /**
     * Execute the console command.
     *
     * A single call to the assistant, with no access to the user's actual
     * balance, recurring subscriptions, or budget: whatever it answers is,
     * at best, a guess informed by typical spending patterns, not a
     * conclusion grounded in this user's real data.
     */
    public function handle(): int
    {
        $rawAmount = $this->argument('amount');
        $description = $this->argument('description');

        if (! is_numeric($rawAmount) || (float) $rawAmount <= 0) {
            $this->components->error("Amount must be a positive number, got \"{$rawAmount}\".");

            return Command::INVALID;
        }

        $amount = (float) $rawAmount;

        $prompt = sprintf(
            'Can I afford to spend %.2f dollars on %s? Answer with a short yes or no and a brief reason.',
            $amount,
            $description,
        );

        $response = (new FinanceAssistant)->prompt($prompt);

        $this->line($response->text);

        return Command::SUCCESS;
    }
}
