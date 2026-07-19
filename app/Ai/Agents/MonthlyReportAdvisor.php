<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetBudgetStatusTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Given the single goal of producing this month's spending report, decides
 * for itself which spending categories are worth checking and in what
 * order, one GetBudgetStatusTool call per category: the same reasoning-
 * action loop already built for PurchaseAdvisor, applied here to a task
 * that, unlike a purchase evaluation, actually has a fixed and known shape
 * ahead of time, every category config('finance.budgets') tracks, checked
 * once each.
 *
 * Nothing here enforces that shape. Whether every category is actually
 * checked, and how many tool calls that costs before the model decides it
 * has enough, is left entirely to the model's own judgment call.
 */
#[MaxSteps(5)]
class MonthlyReportAdvisor implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You produce a short monthly spending report. Check the budget
        status of the spending categories you judge relevant this month,
        one tool call per category, before concluding. Do not guess any
        figure, always retrieve it.

        Summarize what you found in one short sentence per category you
        actually checked.
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
            'summary' => $schema->string()->required(),
        ];
    }
}
