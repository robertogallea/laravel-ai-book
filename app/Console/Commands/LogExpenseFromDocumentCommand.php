<?php

namespace App\Console\Commands;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\ImportedDocumentReader;
use App\Console\Commands\Concerns\DisclosesAiInteraction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:log-expense-from-document {text : Text imported from an email or document}')]
#[Description('Extract an expense from a piece of text imported from an email or document')]
class LogExpenseFromDocumentCommand extends Command
{
    use DisclosesAiInteraction;

    /**
     * How many times to ask the extractor again after an invalid structured
     * response, before giving up. Same bound already used for expenses
     * described directly by the user.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * How much of an imported document is actually sent to the reader.
     * An expense worth logging states its amount, place, and date in a
     * sentence or two, never in thousands of characters: nothing this
     * command does needs the rest of a long statement or email thread,
     * so nothing beyond this length ever leaves the application.
     */
    private const MAX_IMPORTED_TEXT_LENGTH = 4000;

    /**
     * How many consecutive words of the reader's own instructions, once
     * case, whitespace, and punctuation are normalized away, count as an
     * unmistakable quote. Short enough that quoting only part of a
     * sentence is still caught, long enough not to trigger on an
     * unremarkable handful of shared words.
     */
    private const MIN_QUOTED_WORDS = 6;

    /**
     * Phrases a genuine expense description has no reason to use: talking
     * about the reader's own configuration or instructions instead of an
     * amount, a place, and a date. Catches a paraphrase that shares no
     * long run of words with the instructions themselves.
     *
     * @var string[]
     */
    private const SELF_REFERENTIAL_PHRASES = [
        'my instructions',
        'my configuration',
        'my guidelines',
        'my operating guidelines',
        'system instructions',
        'system prompt',
        'operating guidelines',
        'i was configured',
        'i was instructed',
    ];

    /**
     * Execute the console command.
     *
     * Imported text does not come from the user typing in the chat: it may
     * be the body of an email or a document, so it is read here before the
     * usual structured extraction is attempted on it. Whatever reaches the
     * reader is passed on its own, never merged with any other instruction
     * at request time: the extraction instruction lives only in the
     * reader's own instructions, fixed ahead of time. What reaches the
     * reader is capped in length first: a full statement or email thread
     * has no reason to leave the application in its entirety just to
     * extract one expense from it. A document actually cut by that cap
     * is reported as such: a failure to find a recognizable expense past
     * this point should never look identical to a genuinely unparseable
     * document.
     */
    public function handle(): int
    {
        $this->discloseAiInteraction();

        $rawText = $this->argument('text');
        $importedText = mb_substr($rawText, 0, self::MAX_IMPORTED_TEXT_LENGTH);

        if (mb_strlen($rawText) > self::MAX_IMPORTED_TEXT_LENGTH) {
            $this->components->warn(sprintf(
                'The imported text was truncated to its first %d characters before being read.',
                self::MAX_IMPORTED_TEXT_LENGTH,
            ));
        }

        try {
            $description = (new ImportedDocumentReader)->prompt($importedText)->text;
        } catch (\Throwable $e) {
            $this->components->error("The imported text could not be read: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (trim($description) === '') {
            $this->components->error('The imported text did not contain a recognizable expense.');

            return Command::FAILURE;
        }

        if ($this->looksLikeALeakedInstruction($description)) {
            $this->components->error('The imported text could not be processed safely and was discarded.');

            return Command::FAILURE;
        }

        return $this->extract($description);
    }

    /**
     * Check the reader's output for security purposes, distinct from the
     * format validation performed after extraction below: this looks for
     * signs that the imported text managed to redirect the reader into
     * talking about itself instead of merely describing the expense.
     *
     * Two independent signals are checked, since neither is reliable on
     * its own: a near-verbatim quote of the reader's own instructions, and
     * a handful of self-referential phrases a genuine expense description
     * would never need. Neither signal is a guarantee (a paraphrase using
     * none of these words and phrases would still slip through), which is
     * exactly why this check exists alongside isolation rather than
     * instead of it.
     */
    private function looksLikeALeakedInstruction(string $description): bool
    {
        $description = $this->normalize($description);

        return $this->overlapsInstructions($description)
            || $this->talksAboutItsOwnConfiguration($description);
    }

    /**
     * Check for a run of consecutive words shared with the reader's own
     * instructions. Comparing word by word, after normalizing case,
     * whitespace, and punctuation on both sides, means a quote that is
     * only partial, reworded in case, or reflowed onto different lines
     * is still caught, not just a byte-for-byte copy of a whole sentence.
     */
    private function overlapsInstructions(string $description): bool
    {
        $instructions = $this->normalize((string) (new ImportedDocumentReader)->instructions());
        $words = explode(' ', $instructions);

        for ($i = 0; $i <= count($words) - self::MIN_QUOTED_WORDS; $i++) {
            $fragment = implode(' ', array_slice($words, $i, self::MIN_QUOTED_WORDS));

            if (str_contains($description, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for language a genuine expense description has no reason to
     * use: talking about the reader's own configuration or instructions.
     */
    private function talksAboutItsOwnConfiguration(string $description): bool
    {
        foreach (self::SELF_REFERENTIAL_PHRASES as $phrase) {
            if (str_contains($description, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercase the text, strip punctuation, and collapse whitespace, so
     * comparisons are not thrown off by case, line wraps, or punctuation
     * that differs between the fixed instructions and the model's output.
     */
    private function normalize(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Extract amount, category, and date from a free-text description,
     * exactly as already done for expenses described directly by the user.
     */
    private function extract(string $description): int
    {
        $prompt = $description;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = (new ExpenseExtractor)->prompt($prompt);
            } catch (\Throwable $e) {
                $this->components->error("The expense extractor could not be reached: {$e->getMessage()}");

                return Command::FAILURE;
            }

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
