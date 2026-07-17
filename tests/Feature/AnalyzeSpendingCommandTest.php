<?php

namespace Tests\Feature;

use App\Ai\Agents\SpendingAnalyst;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\AiException;
use Mockery;
use RuntimeException;
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

    public function test_an_unexpected_analysis_can_be_reconstructed_from_its_trace(): void
    {
        Log::spy();

        // Same disagreeing total as the uncertainty test above: a user
        // reporting this reply as suspicious now gives the developer
        // exactly what was sent and received for that specific exchange.
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 999.99, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('llm_call', Mockery::on(function (array $trace) {
                return $trace['prompt'] === 'Category: groceries. Known transactions so far this month (dollars): 42.50, 18.00.'
                    && str_contains($trace['response'], '999.99')
                    && $trace['tokens'] === 0
                    && $trace['guardrail_outcome'] === null
                    && is_string($trace['timestamp']);
            }));
    }

    public function test_a_retried_call_only_traces_the_attempt_that_succeeded(): void
    {
        Sleep::fake();
        Log::spy();

        $attempt = 0;

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
        ])->assertExitCode(0);

        Log::shouldHaveReceived('info')->with('llm_call', Mockery::any())->once();
    }

    public function test_falling_back_after_exhausting_every_retry_traces_nothing(): void
    {
        Sleep::fake();
        Log::spy();

        SpendingAnalyst::fake([
            fn () => throw new AiException('The AI service did not respond in time.'),
            fn () => throw new AiException('The AI service did not respond in time.'),
            fn () => throw new AiException('The AI service did not respond in time.'),
        ]);

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])->assertExitCode(1);

        Log::shouldNotHaveReceived('info');
    }

    public function test_a_broken_log_channel_does_not_prevent_a_successful_analysis_from_being_shown(): void
    {
        SpendingAnalyst::fake([
            ['total_spent_so_far' => 60.50, 'insight' => 'Your grocery spending looks stable so far.'],
        ]);

        Log::shouldReceive('info')
            ->once()
            ->andThrow(new RuntimeException('log channel unavailable'));

        $this->artisan('assistant:analyze-spending', [
            'category' => 'groceries',
            'amounts' => ['42.50', '18.00'],
        ])
            ->expectsOutputToContain('Total spent so far on groceries: 60.50 dollars')
            ->expectsOutputToContain('Your grocery spending looks stable so far.')
            ->assertExitCode(0);
    }
}
