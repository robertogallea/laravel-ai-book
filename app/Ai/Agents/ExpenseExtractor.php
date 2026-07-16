<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ExpenseExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The categories the schema below constrains the model to.
     *
     * "other" is included on purpose, as an escape valve for expenses that
     * do not fit any of the concrete categories: without it, an otherwise
     * legitimate expense would have no valid value to report.
     *
     * @var string[]
     */
    public const CATEGORIES = [
        'groceries', 'restaurants', 'transportation', 'entertainment', 'utilities', 'other',
    ];

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Extract the amount, category, and date of the described expense.';
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number()->required(),
            'category' => $schema->string()->required()->enum(self::CATEGORIES),
            'date' => $schema->string()->required()->pattern('^\d{4}-\d{2}-\d{2}$'),
        ];
    }
}
