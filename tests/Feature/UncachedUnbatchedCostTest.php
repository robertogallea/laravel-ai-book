<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use App\Ai\Agents\MonthlyReportSummarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Tests\TestCase;

/**
 * A single, repeatable test case, aggregated with the same observability
 * built in the chapter on resilience: three identical spending questions,
 * nothing about the user's transactions having changed between one and
 * the next, and three separate, individually triggered requests for this
 * month's report. Neither AskSpendingCommand nor GenerateMonthlyReportCommand
 * has any way, yet, to avoid paying for each of these six calls in full.
 */
class UncachedUnbatchedCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_questions_and_individually_generated_reports_are_each_billed_in_full(): void
    {
        $tracedTokens = [];

        Log::shouldReceive('info')->andReturnUsing(function (string $message, array $context) use (&$tracedTokens) {
            if ($message === 'llm_call') {
                $tracedTokens[] = $context['tokens'];
            }
        });

        FinanceAssistant::fake(fn () => new TextResponse(
            'You spent $120.00 on restaurants this month.',
            new Usage(promptTokens: 80, completionTokens: 20),
            new Meta('fake', 'fake-model'),
        ));

        MonthlyReportSummarizer::fake(fn () => new StructuredTextResponse(
            ['summary' => 'Spending is within budget this month.'],
            json_encode(['summary' => 'Spending is within budget this month.']),
            new Usage(promptTokens: 150, completionTokens: 40),
            new Meta('fake', 'fake-model'),
        ));

        // Same question, asked three times in a row: nothing about the
        // user's transactions changed in between.
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('assistant:ask-spending', [
                'question' => 'How much did I spend on restaurants this month?',
            ])->assertExitCode(0);
        }

        // Three separate, individually triggered requests for the exact
        // same month's report, none of them urgent.
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('assistant:generate-monthly-report')->assertExitCode(0);
        }

        // Six calls traced, none of them avoided: 100 tokens per question
        // (80 + 20) times three, plus 190 tokens per report (150 + 40)
        // times three.
        $this->assertCount(6, $tracedTokens);
        $this->assertSame(3 * 100 + 3 * 190, array_sum($tracedTokens));
    }
}
