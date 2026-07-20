<?php

namespace App\Console\Commands;

use App\Ai\Agents\PurchaseAdvisor;
use App\Support\Eval\EvalCase;
use App\Support\Eval\EvalRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:eval-purchase-advisor')]
#[Description('Run the purchase-advisor eval set against the current prompt and gate on a full pass')]
class EvalPurchaseAdvisorCommand extends Command
{
    /**
     * Reference cases for the planning agent (Chapter 8). Unlike the
     * categorization cases above, a single field rarely settles whether a
     * response is correct here: the agent's verdict is the outcome of a
     * multi-step, non-deterministic reasoning loop, so each criterion
     * below checks several conditions together, closer to a judge's rubric
     * than to a plain equality check.
     *
     * @return EvalCase[]
     */
    private function cases(): array
    {
        return [
            new EvalCase(
                name: 'comfortably_affordable_purchase',
                input: 'Can the user afford to spend 40.00 dollars on a pair of headphones?',
                criterion: fn (array $s) => ($s['affordable'] ?? null) === true
                    && is_string($s['reasoning'] ?? null) && $s['reasoning'] !== ''
                    && ($s['suggested_action'] ?? null) === null,
            ),
            new EvalCase(
                name: 'unaffordable_purchase_with_unused_subscription',
                input: 'Can the user afford to spend 600.00 dollars on a new laptop?',
                criterion: fn (array $s) => ($s['affordable'] ?? null) === false
                    && is_string($s['reasoning'] ?? null) && $s['reasoning'] !== ''
                    && is_array($s['suggested_action'] ?? null)
                    && ($s['suggested_action']['subscription_name'] ?? null) === 'Streaming Plus',
            ),
        ];
    }

    public function handle(): int
    {
        $cases = $this->cases();

        $failed = EvalRunner::run(
            $cases,
            fn (string $input) => (new PurchaseAdvisor)->prompt($input)->structured,
        );

        $total = count($cases);
        $passed = $total - count($failed);

        $this->line("Purchase-advisor eval: {$passed}/{$total} cases passed.");

        if ($failed !== []) {
            $this->components->error('Failed cases: '.implode(', ', $failed));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
