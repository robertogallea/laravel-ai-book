<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\FinanceAssistant;
use App\Ai\Agents\ImportedDocumentReader;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Demonstrates, with a test for each, the two gaps DATA-SENT-TO-MODEL-AUDIT.md
 * identifies: an imported document sent to the model with no cap on its
 * length, and no command ever disclosing to the user that they are talking
 * to an automated system.
 */
class DataSentToModelReviewTest extends TestCase
{
    public function test_an_arbitrarily_long_imported_document_is_sent_to_the_model_in_full(): void
    {
        $longImportedText = 'Restaurant charge: $38.20, dated 2026-07-14. '.Str::repeat('Unrelated boilerplate. ', 500);

        ImportedDocumentReader::fake([
            'A restaurant charge of $38.20 dated 2026-07-14.',
        ]);

        ExpenseExtractor::fake([
            ['amount' => 38.20, 'category' => 'restaurants', 'date' => '2026-07-14'],
        ]);

        $this->artisan('assistant:log-expense-from-document', ['text' => $longImportedText])
            ->assertExitCode(0);

        // Nothing bounds this today: the entire text, all 12000+ characters
        // of it, reaches the reader exactly as imported.
        ImportedDocumentReader::assertPrompted(
            fn ($prompt) => $prompt->prompt === $longImportedText
        );
    }

    public function test_no_command_discloses_that_the_user_is_talking_to_an_automated_system(): void
    {
        FinanceAssistant::fake(['Here is your spending summary.']);

        $this->artisan('assistant:ask', ['question' => 'How much did I spend this month?'])
            ->doesntExpectOutputToContain('automated')
            ->doesntExpectOutputToContain('AI assistant')
            ->assertExitCode(0);
    }
}
