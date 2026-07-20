<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\FinanceAssistant;
use App\Ai\Agents\ImportedDocumentReader;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DATA-SENT-TO-MODEL-AUDIT.md identified two gaps: an imported document sent
 * to the model with no cap on its length, and no command ever disclosing to
 * the user that they are talking to an automated system. Both are fixed by
 * this point: an imported document is capped before it reaches the model,
 * and every command that puts the user in direct conversation with an agent
 * discloses that upfront.
 */
class DataSentToModelReviewTest extends TestCase
{
    public function test_an_imported_document_is_capped_before_it_reaches_the_model(): void
    {
        $longImportedText = 'Restaurant charge: $38.20, dated 2026-07-14. '.Str::repeat('Unrelated boilerplate. ', 500);
        $this->assertGreaterThan(4000, strlen($longImportedText));

        ImportedDocumentReader::fake([
            'A restaurant charge of $38.20 dated 2026-07-14.',
        ]);

        ExpenseExtractor::fake([
            ['amount' => 38.20, 'category' => 'restaurants', 'date' => '2026-07-14'],
        ]);

        $this->artisan('assistant:log-expense-from-document', ['text' => $longImportedText])
            ->assertExitCode(0);

        ImportedDocumentReader::assertPrompted(
            fn ($prompt) => $prompt->prompt !== $longImportedText
                && strlen($prompt->prompt) <= 4000
                && str_starts_with($longImportedText, $prompt->prompt)
        );
    }

    public function test_a_short_imported_document_reaches_the_model_unchanged(): void
    {
        $shortImportedText = 'Restaurant charge: $38.20, dated 2026-07-14.';

        ImportedDocumentReader::fake([
            'A restaurant charge of $38.20 dated 2026-07-14.',
        ]);

        ExpenseExtractor::fake([
            ['amount' => 38.20, 'category' => 'restaurants', 'date' => '2026-07-14'],
        ]);

        $this->artisan('assistant:log-expense-from-document', ['text' => $shortImportedText])
            ->assertExitCode(0);

        ImportedDocumentReader::assertPrompted(
            fn ($prompt) => $prompt->prompt === $shortImportedText
        );
    }

    public function test_a_one_off_question_discloses_the_automated_interaction_before_answering(): void
    {
        FinanceAssistant::fake(['Here is your spending summary.']);

        $this->artisan('assistant:ask', ['question' => 'How much did I spend this month?'])
            ->expectsOutputToContain('You are talking to an automated AI assistant, not a human.')
            ->assertExitCode(0);
    }
}
