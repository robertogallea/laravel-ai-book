<?php

namespace App\Console\Commands;

use App\Ai\Agents\SpendingAnalyst;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:analyze-spending {category : Spending category to analyze} {amounts* : Known transaction amounts in this category so far this month}')]
#[Description('Ask the assistant for the total spent so far in a category and a short insight about the trend')]
class AnalyzeSpendingCommand extends Command
{
    /**
     * Execute the console command.
     *
     * A single, unguarded call to the assistant: a provider failure has
     * nowhere to go but up, and the reported total is printed exactly as
     * received, with no check against the transactions the caller already
     * knows about.
     */
    public function handle(): int
    {
        $category = $this->argument('category');
        $amounts = array_map('floatval', $this->argument('amounts'));

        $response = (new SpendingAnalyst)->prompt(sprintf(
            'Category: %s. Known transactions so far this month (dollars): %s.',
            $category,
            implode(', ', array_map(fn ($amount) => number_format($amount, 2), $amounts)),
        ));

        $this->line(sprintf(
            'Total spent so far on %s: %.2f dollars',
            $category,
            $response->structured['total_spent_so_far'],
        ));
        $this->line($response->structured['insight']);

        return Command::SUCCESS;
    }
}
