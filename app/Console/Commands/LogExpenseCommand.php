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
     * How many times to ask the model again after an invalid structured
     * response, before giving up. Bounded on purpose: correcting a bad
     * response is worth a few attempts, not an unlimited number of them.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Execute the console command.
     *
     * Asks the assistant for a schema-constrained structured response, then
     * validates it in code before trusting it: the schema makes conformance
     * likely, not certain. An invalid response is corrected with a targeted
     * follow-up naming exactly what was wrong, up to a bounded number of
     * attempts, with an explicit fallback once those are exhausted.
     */
    public function handle(): int
    {
        $description = $this->argument('description');
        $prompt = $description;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = (new ExpenseExtractor)->prompt($prompt);

            $errors = $this->validate($response->structured);

            if (empty($errors)) {
                $this->line("Amount: {$response->structured['amount']}");
                $this->line("Category: {$response->structured['category']}");
                $this->line("Date: {$response->structured['date']}");

                return Command::SUCCESS;
            }

            $prompt = sprintf(
                'The previous extraction was invalid: %s. The expense was: "%s". Try again.',
                implode(', ', $errors),
                $description,
            );
        }

        $this->components->error(sprintf(
            'Could not extract a valid expense after %d attempts. Please rephrase and try again.',
            self::MAX_ATTEMPTS,
        ));

        return Command::FAILURE;
    }

    /**
     * Check a structured response against the same constraints declared in
     * the schema. The package does not enforce these itself, so the
     * application checks them before trusting the response.
     *
     * @param  array<string, mixed>  $structured
     * @return string[]
     */
    private function validate(array $structured): array
    {
        $errors = [];

        if (! array_key_exists('amount', $structured) || ! is_numeric($structured['amount'])) {
            $errors[] = 'amount is missing or not numeric';
        }

        if (! array_key_exists('category', $structured) || ! in_array($structured['category'], ExpenseExtractor::CATEGORIES, true)) {
            $errors[] = 'category is missing or not one of: '.implode(', ', ExpenseExtractor::CATEGORIES);
        }

        if (! array_key_exists('date', $structured) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $structured['date'])) {
            $errors[] = 'date is missing or not in YYYY-MM-DD format';
        }

        return $errors;
    }
}
