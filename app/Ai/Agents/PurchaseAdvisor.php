<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetAccountBalanceTool;
use App\Ai\Tools\GetBudgetStatusTool;
use App\Ai\Tools\GetRecurringExpensesTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Given a purchase to evaluate, decides for itself which of its tools to
 * call and in what order, before concluding: the account balance, the
 * recurring subscriptions, and the budget status of whichever category the
 * purchase belongs to are not fetched up front by the caller, the way
 * earlier chapters' single-call agents always received everything they
 * needed already folded into the prompt.
 *
 * The bound below is what stands between this loop and an unbounded one:
 * five steps are enough for every tool available here to be called at
 * least once, with room for a repeated or corrective call, but not enough
 * to let an unproductive loop run indefinitely.
 *
 * Each of those steps chains onto the last one's observation before this
 * agent commits to a conclusion: a smaller, cheaper model routed here to
 * save on the categorization task above would be reasoning about the same
 * kind of multi-step, interdependent decision on a fraction of the budget
 * that decision deserves.
 */
#[MaxSteps(5)]
#[UseSmartestModel]
class PurchaseAdvisor implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You help the user decide whether a purchase is affordable. You are
        given the purchase amount and a short description of it.

        Before concluding, consult the tools available to you: the current
        account balance, the recurring subscriptions, and the budget status
        of the spending category the purchase most likely belongs to. Do
        not guess any of these values, always retrieve them.

        Only if an existing subscription has gone unused for a long time,
        and cancelling it would make an otherwise unaffordable purchase fit
        the budget, include it as a suggested action. Never suggest
        cancelling a subscription that is still in regular use, and never
        suggest one if the purchase is already affordable without it.
        TEXT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetAccountBalanceTool,
            new GetRecurringExpensesTool,
            new GetBudgetStatusTool,
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'affordable' => $schema->boolean()->required(),
            'reasoning' => $schema->string()->required(),
            'suggested_action' => $schema->object([
                'subscription_name' => $schema->string()->required(),
                'monthly_cost' => $schema->number()->required(),
                'days_unused' => $schema->integer()->required(),
            ])->nullable(),
        ];
    }
}
