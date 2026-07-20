<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use App\Ai\Agents\MonthlyReportSummarizer;
use App\Models\ReportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Tests\TestCase;

/**
 * Reruns the exact scenario measured at the previous tag
 * (ch11-uncached-unbatched-cost, see the now-removed
 * UncachedUnbatchedCostTest): three identical spending questions and three
 * individually triggered monthly-report requests, which traced 870 tokens
 * in full (100 per question times three, 190 per report times three),
 * none of the six calls avoided. This test measures the same scenario
 * after caching and batching, and compares the two numbers explicitly
 * instead of only asserting that the corrected version is cheaper.
 */
class CachedBatchedCostComparisonTest extends TestCase
{
    use RefreshDatabase;

    private const PREVIOUSLY_MEASURED_UNCACHED_UNBATCHED_TOKENS = 870;

    public function test_the_same_scenario_costs_less_once_caching_and_batching_are_in_place(): void
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

        // Same question, asked three times in a row: the second and third
        // are answered from cache, no call made, nothing traced.
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('assistant:ask-spending', [
                'question' => 'How much did I spend on restaurants this month?',
            ])->assertExitCode(0);
        }

        // Three separate, non-urgent requests for the exact same month's
        // report, queued instead of generated immediately.
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('assistant:request-monthly-report')->assertExitCode(0);
        }

        $this->assertSame(3, ReportRequest::where('status', 'pending')->count());

        // One scheduled batch answers all three requests with a single run.
        $this->artisan('assistant:process-report-queue')
            ->expectsOutputToContain(sprintf(
                'Processed 3 pending report request(s) for %s in a single batched run.',
                now()->format('Y-m'),
            ))
            ->assertExitCode(0);

        $this->assertSame(3, ReportRequest::where('status', 'processed')->count());

        // One real question answered (80 + 20 tokens), one real report
        // generated (150 + 40 tokens): 290 tokens total, against 870
        // before, for the exact same six original requests.
        $this->assertCount(2, $tracedTokens);
        $totalTokens = array_sum($tracedTokens);
        $this->assertSame(100 + 190, $totalTokens);
        $this->assertLessThan(self::PREVIOUSLY_MEASURED_UNCACHED_UNBATCHED_TOKENS, $totalTokens);
    }
}
