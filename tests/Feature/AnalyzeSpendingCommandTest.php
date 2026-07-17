<?php

namespace Tests\Feature;

use App\Ai\Agents\SpendingAnalyst;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\AiException;
use Tests\TestCase;

class AnalyzeSpendingCommandTest extends TestCase
{
    public function test_it_prints_the_assistants_analysis_when_the_total_agrees(): void
    {
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 60.50, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->expectsOutputToContain('Your grocery spending looks stable so far.')
            ->doesntExpectOutputToContain('approximate')
            ->assertExitCode(0);
    }

    public function test_a_reported_total_that_disagrees_with_known_transactions_is_flagged_as_uncertain(): void
    {
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 999.99, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->expectsOutputToContain('This insight might be approximate')
            ->assertExitCode(0);
    }

    public function test_it_retries_a_technical_failure_with_backoff_and_recovers(): void
    {
        Sleep::fake();

        $attempt = 0;

        // A single response array only advances its index after a
        // successful attempt (see FakeTextGateway::nextResponse), so a
        // sequence that fails and then succeeds needs its own counter
        // instead of relying on that indexing.
        SpendingAnalyst::fake(function () use (&$attempt) {
            $attempt++;

            if ($attempt === 1) {
                throw new AiException('The AI service did not respond in time.');
            }

            return ['total_spent_so_far' => 60.50, 'insight' => 'Your grocery spending looks stable so far.'];
        });

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->assertExitCode(0);

        Sleep::assertSequence([
            Sleep::for(200)->milliseconds(),
        ]);
    }

    public function test_it_falls_back_after_exhausting_every_retry(): void
    {
        Sleep::fake();

        SpendingAnalyst::fake([
            fn () => throw new AiException('The AI service did not respond in time.'),
            fn () => throw new AiException('The AI service did not respond in time.'),
            fn () => throw new AiException('The AI service did not respond in time.'),
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Could not analyze spending on groceries right now, after 3 attempts')
            ->assertExitCode(1);

        Sleep::assertSequence([
            Sleep::for(200)->milliseconds(),
            Sleep::for(400)->milliseconds(),
        ]);
    }

    public function test_it_retries_a_real_connection_timeout_and_recovers(): void
    {
        Sleep::fake();

        $attempt = 0;

        // Laravel's own HTTP client throws ConnectionException for a real
        // timeout or dropped connection, not AiException: this is the
        // shape a real provider outage actually raises, unlike the
        // AiException used as a stand-in in the retry test above.
        SpendingAnalyst::fake(function () use (&$attempt) {
            $attempt++;

            if ($attempt === 1) {
                throw new ConnectionException('cURL error 28: Connection timed out.');
            }

            return ['total_spent_so_far' => 60.50, 'insight' => 'Your grocery spending looks stable so far.'];
        });

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->assertExitCode(0);

        Sleep::assertSequence([
            Sleep::for(200)->milliseconds(),
        ]);
    }

    public function test_it_falls_back_after_exhausting_every_retry_on_connection_timeouts(): void
    {
        Sleep::fake();

        SpendingAnalyst::fake([
            fn () => throw new ConnectionException('cURL error 28: Connection timed out.'),
            fn () => throw new ConnectionException('cURL error 28: Connection timed out.'),
            fn () => throw new ConnectionException('cURL error 28: Connection timed out.'),
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Could not analyze spending on groceries right now, after 3 attempts')
            ->assertExitCode(1);

        Sleep::assertSequence([
            Sleep::for(200)->milliseconds(),
            Sleep::for(400)->milliseconds(),
        ]);
    }
}
