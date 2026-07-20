<?php

namespace App\Console\Commands;

use App\Ai\Agents\PurchaseAdvisor;
use App\Console\Commands\Concerns\ReadsStructuredResponse;
use App\Console\Commands\Concerns\RunsEvalSet;
use App\Models\User;
use App\Support\Eval\EvalCase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:eval-purchase-advisor')]
#[Description('Run the purchase-advisor eval set against the current prompt and gate on a full pass')]
class EvalPurchaseAdvisorCommand extends Command
{
    use ReadsStructuredResponse;
    use RunsEvalSet;

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
                    && $this->stringField($s, 'reasoning') !== null
                    && ($s['suggested_action'] ?? null) === null,
            ),
            new EvalCase(
                name: 'unaffordable_purchase_with_unused_subscription',
                input: 'Can the user afford to spend 600.00 dollars on a new laptop?',
                criterion: fn (array $s) => ($s['affordable'] ?? null) === false
                    && $this->stringField($s, 'reasoning') !== null
                    && is_array($s['suggested_action'] ?? null)
                    && ($s['suggested_action']['subscription_name'] ?? null) === 'Streaming Plus',
            ),
        ];
    }

    public function handle(): int
    {
        // A case here evaluates the agent's reasoning against a synthetic
        // scenario, not a real account: an unpersisted placeholder user,
        // never written to the database, is all PurchaseAdvisor's
        // constructor needs to run.
        $evalUser = new User;

        return $this->runEvalSet(
            'Purchase-advisor',
            $this->cases(),
            fn (string $input) => (new PurchaseAdvisor($evalUser))->prompt($input)->structured,
        );
    }
}
