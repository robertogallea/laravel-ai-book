<?php

namespace App\Console\Commands;

use App\Ai\Agents\ExpenseExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:log-expense {description : A free-text description of an expense}')]
#[Description('Extract amount, category, and date from a described expense')]
class LogExpenseCommand extends Command
{
    /**
     * The categories the naive parser below knows how to recognize.
     *
     * This is deliberately a plain keyword list, not a schema: it is the
     * fragile, free-text-parsing version of this command, and is meant to
     * break on wording it was not written to expect.
     *
     * @var string[]
     */
    private const CATEGORIES = [
        'groceries', 'restaurants', 'transportation', 'entertainment', 'utilities',
    ];

    /**
     * Execute the console command.
     *
     * Asks the assistant to restate the expense in prose, then tries to pull
     * amount, category, and date back out of that prose with ad hoc parsing.
     * This is the version the rest of the chapter's example builds on: it
     * works for phrasing the parser anticipated, and breaks silently or
     * loudly otherwise.
     */
    public function handle(): int
    {
        $description = $this->argument('description');

        $reply = (new ExpenseExtractor)->prompt($description)->text;

        $amount = $this->extractAmount($reply);
        $category = $this->extractCategory($reply);
        $date = $this->extractDate($reply);

        $missing = array_keys(array_filter([
            'amount' => $amount === null,
            'category' => $category === null,
            'date' => $date === null,
        ]));

        if (! empty($missing)) {
            $this->components->error(sprintf(
                'Could not parse %s from the assistant\'s reply: "%s"',
                implode(', ', $missing),
                $reply,
            ));

            return Command::FAILURE;
        }

        $this->line("Amount: {$amount}");
        $this->line("Category: {$category}");
        $this->line("Date: {$date}");

        return Command::SUCCESS;
    }

    /**
     * Look for a number immediately followed by the word "dollars".
     */
    private function extractAmount(string $text): ?float
    {
        if (preg_match('/(\d+(?:\.\d{1,2})?)\s*dollars/i', $text, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Look for one of the known category keywords anywhere in the text.
     */
    private function extractCategory(string $text): ?string
    {
        $lower = strtolower($text);

        foreach (self::CATEGORIES as $category) {
            if (str_contains($lower, $category)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Look for a date already written in YYYY-MM-DD form.
     */
    private function extractDate(string $text): ?string
    {
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
