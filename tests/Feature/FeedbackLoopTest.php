<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Models\EvalFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closes the loop left open by StaticEvalDatasetMissesRealCaseTest: the
 * same refund-adjustment case, miscategorized as "other" instead of
 * "restaurants", flagged with negative feedback, confirmed by a reviewer,
 * and only then folded into the categorization eval set.
 */
class FeedbackLoopTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTION = "Refund adjustment for an earlier restaurant charge at Luigi's Trattoria";

    public function test_positive_feedback_is_acknowledged_but_never_queued(): void
    {
        $this->artisan('assistant:submit-feedback', [
            'description' => self::DESCRIPTION,
            'category' => 'restaurants',
            'rating' => 'positive',
        ])
            ->expectsOutputToContain('Thanks for the feedback.')
            ->assertExitCode(0);

        $this->assertSame(0, EvalFeedback::count());
    }

    public function test_negative_feedback_is_queued_for_review_instead_of_trusted_directly(): void
    {
        $this->artisan('assistant:submit-feedback', [
            'description' => self::DESCRIPTION,
            'category' => 'other',
            'rating' => 'negative',
        ])
            ->expectsOutputToContain('Feedback recorded. This case has been queued for review.')
            ->assertExitCode(0);

        $feedback = EvalFeedback::sole();

        $this->assertSame('pending_review', $feedback->status);
        $this->assertNull($feedback->expected_category);
    }

    public function test_a_dismissed_case_is_never_added_to_the_eval_set(): void
    {
        EvalFeedback::create([
            'input' => self::DESCRIPTION,
            'category' => 'other',
            'status' => 'pending_review',
        ]);

        $this->artisan('assistant:review-feedback')
            ->expectsConfirmation('Is this a genuine miscategorization?', 'no')
            ->expectsOutputToContain('Dismissed: not added to the eval dataset.')
            ->assertExitCode(0);

        $this->assertSame('dismissed', EvalFeedback::sole()->status);
    }

    public function test_a_confirmed_case_becomes_a_new_eval_case_that_catches_the_same_mistake_again(): void
    {
        EvalFeedback::create([
            'input' => self::DESCRIPTION,
            'category' => 'other',
            'status' => 'pending_review',
        ]);

        $this->artisan('assistant:review-feedback')
            ->expectsConfirmation('Is this a genuine miscategorization?', 'yes')
            ->expectsChoice(
                'What is the correct category?',
                'restaurants',
                ['groceries', 'restaurants', 'transportation', 'entertainment', 'utilities', 'other'],
            )
            ->expectsOutputToContain('Confirmed: added to the eval dataset as "restaurants".')
            ->assertExitCode(0);

        $feedback = EvalFeedback::sole();
        $this->assertSame('confirmed', $feedback->status);
        $this->assertSame('restaurants', $feedback->expected_category);

        // The eval set now includes this case: run the same unresolved
        // mistake through it, and it is no longer invisible.
        ExpenseExtractor::fake([
            ['amount' => 24.90, 'category' => 'restaurants', 'date' => '2026-07-16'],
            ['amount' => 55.00, 'category' => 'transportation', 'date' => '2026-07-16'],
            ['amount' => 78.30, 'category' => 'utilities', 'date' => '2026-07-16'],
            ['amount' => 12.40, 'category' => 'groceries', 'date' => '2026-07-16'],
            ['amount' => 8.00, 'category' => 'other', 'date' => '2026-07-16'],
            ['amount' => 8.50, 'category' => 'other', 'date' => '2026-07-20'],
        ]);

        $this->artisan('assistant:eval-categorization')
            ->expectsOutputToContain('Categorization eval: 5/6 cases passed.')
            ->expectsOutputToContain("Failed cases: confirmed_feedback_{$feedback->id}")
            ->assertExitCode(1);
    }
}
