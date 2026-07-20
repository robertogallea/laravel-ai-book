<?php

namespace App\Console\Commands;

use App\Ai\Agents\SpendingAnalyst;
use App\Console\Commands\Concerns\DisclosesAiInteraction;
use App\Support\CallTrace;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

#[Signature('assistant:analyze-spending {category : Spending category to analyze} {amounts* : Known transaction amounts in this category so far this month}')]
#[Description('Ask the assistant for the total spent so far in a category and a short insight about the trend')]
class AnalyzeSpendingCommand extends Command
{
    use DisclosesAiInteraction;

    /**
     * How many times to attempt the call after a technical failure, before
     * giving up and falling back. Bounded on purpose, same reasoning as the
     * retry in the expense extraction command: insisting forever is not
     * resilience, it just delays an eventual failure.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * How long to wait before the first retry. Doubled after every
     * subsequent failed attempt.
     */
    private const INITIAL_BACKOFF_MS = 200;

    /**
     * The largest difference, in dollars, between the assistant's reported
     * total and the total already known from the caller's own transactions
     * that is still treated as the same figure, to absorb rounding.
     */
    private const AGREEMENT_TOLERANCE = 0.01;

    /**
     * Execute the console command.
     *
     * A technical failure (timeout, dropped connection, rate limit) is
     * retried with exponential backoff, up to a bounded number of attempts,
     * with an explicit fallback once those are exhausted. A semantic
     * failure, an analysis that is well-formed but numerically wrong, is a
     * different problem entirely and cannot be retried away: it is instead
     * cross-checked against the transactions already known to the caller,
     * and flagged with an explicit uncertainty note whenever that check
     * does not agree with what the assistant reported.
     */
    public function handle(): int
    {
        $this->discloseAiInteraction();

        $category = $this->argument('category');
        $amounts = array_map('floatval', $this->argument('amounts'));
        $knownTotal = array_sum($amounts);

        $prompt = sprintf(
            'Category: %s. Known transactions so far this month (dollars): %s.',
            $category,
            implode(', ', array_map(fn ($amount) => number_format($amount, 2), $amounts)),
        );

        $response = $this->callWithRetry($prompt, fn () => (new SpendingAnalyst)->prompt($prompt));

        if ($response === null) {
            $this->components->error(sprintf(
                'Could not analyze spending on %s right now, after %d attempts. Please try again in a moment.',
                $category,
                self::MAX_ATTEMPTS,
            ));

            return Command::FAILURE;
        }

        $reportedTotal = (float) $response->structured['total_spent_so_far'];
        $agrees = abs($reportedTotal - $knownTotal) < self::AGREEMENT_TOLERANCE;

        $this->line(sprintf('Total spent so far on %s: %.2f dollars', $category, $knownTotal));
        $this->line($response->structured['insight']);

        if (! $agrees) {
            $this->components->warn(
                'This insight might be approximate, I could not verify it against your most recent transactions.'
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Call the assistant, retrying a technical failure with exponential
     * backoff up to a bounded number of attempts. Returns null once every
     * attempt has been exhausted, leaving the fallback to the caller. Every
     * call that actually completes, however many attempts it took to get
     * there, is traced before being returned: a failed attempt has no
     * response to trace, but the exchange that finally succeeds always does.
     *
     * Both AiException (the failures the package itself recognizes) and
     * HttpClientException are caught: a real timeout or dropped connection
     * surfaces as ConnectionException or RequestException, both subclasses
     * of HttpClientException rather than AiException. Tracing that
     * successful exchange must never be able to take down a call that
     * already succeeded: a broken log channel is a problem to fix on its
     * own, not a reason to discard an answer already in hand.
     */
    private function callWithRetry(string $prompt, Closure $call): ?AgentResponse
    {
        $backoffMs = self::INITIAL_BACKOFF_MS;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $call();
            } catch (AiException|HttpClientException) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    return null;
                }

                Sleep::for($backoffMs)->milliseconds();
                $backoffMs *= 2;

                continue;
            }

            try {
                CallTrace::record($prompt, $response);
            } catch (Throwable) {
                // Intentionally ignored, see above.
            }

            return $response;
        }

        return null;
    }
}
