<?php

namespace App\Console\Commands;

use App\Ai\Agents\ExpenseExtractor;
use App\Models\EvalFeedback;
use App\Support\Eval\EvalCase;
use App\Support\Eval\EvalRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:eval-categorization')]
#[Description('Run the expense-categorization eval set against the current prompt and gate on a full pass')]
class EvalCategorizationCommand extends Command
{
    /**
     * Reference cases for the categorization prompt (Chapter 3). Correctness
     * here reduces to a single equality check: a closed-set classification
     * either lands on the expected category or it does not, no judgment
     * call required to score it. The last two cases exist specifically to
     * catch the kind of regression a prompt change can introduce: an
     * ambiguous description a change is meant to fix, and a genuinely
     * miscellaneous one the same change must not break.
     *
     * @return EvalCase[]
     */
    private function cases(): array
    {
        return [
            new EvalCase(
                name: 'restaurant_charge',
                input: "Charge of 24.90 dollars at Luigi's Trattoria",
                criterion: fn (array $s) => ($s['category'] ?? null) === 'restaurants',
            ),
            new EvalCase(
                name: 'gas_station_fill_up',
                input: 'Fill-up at Shell gas station, 55 dollars',
                criterion: fn (array $s) => ($s['category'] ?? null) === 'transportation',
            ),
            new EvalCase(
                name: 'electricity_bill',
                input: 'Monthly electricity bill payment, 78.30 dollars',
                criterion: fn (array $s) => ($s['category'] ?? null) === 'utilities',
            ),
            new EvalCase(
                name: 'ambiguous_corner_shop_charge',
                input: 'Charge from Corner Market, which sells both groceries and coffee to go',
                criterion: fn (array $s) => ($s['category'] ?? null) === 'groceries',
            ),
            new EvalCase(
                name: 'genuinely_miscellaneous_fee',
                input: 'One-time miscellaneous membership renewal fee, not tied to any specific service',
                criterion: fn (array $s) => ($s['category'] ?? null) === 'other',
            ),
            ...$this->confirmedFeedbackCases(),
        ];
    }

    /**
     * Cases grown from real usage instead of written up front: each one
     * comes from a user's negative rating that a reviewer has since
     * confirmed and paired with the category the response should have
     * returned (see SubmitFeedbackCommand and ReviewFeedbackCommand). A
     * rating that is still pending, or was dismissed as unfounded, never
     * reaches this list.
     *
     * @return EvalCase[]
     */
    private function confirmedFeedbackCases(): array
    {
        return EvalFeedback::where('status', 'confirmed')
            ->get()
            ->map(fn (EvalFeedback $feedback) => new EvalCase(
                name: "confirmed_feedback_{$feedback->id}",
                input: $feedback->input,
                criterion: fn (array $s) => ($s['category'] ?? null) === $feedback->expected_category,
            ))
            ->all();
    }

    public function handle(): int
    {
        $cases = $this->cases();

        $failed = EvalRunner::run(
            $cases,
            fn (string $input) => (new ExpenseExtractor)->prompt($input)->structured,
        );

        $total = count($cases);
        $passed = $total - count($failed);

        $this->line("Categorization eval: {$passed}/{$total} cases passed.");

        if ($failed !== []) {
            $this->components->error('Failed cases: '.implode(', ', $failed));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
