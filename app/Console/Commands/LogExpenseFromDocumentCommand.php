<?php

namespace App\Console\Commands;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\ImportedDocumentReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:log-expense-from-document {text : Text imported from an email or document}')]
#[Description('Extract an expense from a piece of text imported from an email or document')]
class LogExpenseFromDocumentCommand extends Command
{
    /**
     * How many times to ask the extractor again after an invalid structured
     * response, before giving up. Same bound already used for expenses
     * described directly by the user.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Execute the console command.
     *
     * Imported text does not come from the user typing in the chat: it may
     * be the body of an email or a document, so it is read here before the
     * usual structured extraction is attempted on it. Reading it is simply
     * concatenated onto the reader's own request-time instruction, with no
     * separation between the two.
     */
    public function handle(): int
    {
        $importedText = $this->argument('text');

        $prompt = "Describe, in a single sentence, the amount, place, and date of the expense mentioned in the following imported text:\n\n{$importedText}";

        $description = (new ImportedDocumentReader)->prompt($prompt)->text;

        return $this->extract($description);
    }

    /**
     * Extract amount, category, and date from a free-text description,
     * exactly as already done for expenses described directly by the user.
     */
    private function extract(string $description): int
    {
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
     * the schema, exactly as already done for expenses described directly
     * by the user.
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
