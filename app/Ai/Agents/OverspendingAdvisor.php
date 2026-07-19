<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Called only for the categories GenerateMonthlyReportCommand has already
 * determined are over budget beyond its configured threshold: deciding
 * whether this step runs at all is the pipeline's job, not this agent's.
 */
class OverspendingAdvisor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are given spending categories already known to be over budget this month, with how far '
            .'over each one is. Suggest one brief, concrete recommendation per category given.';
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommendations' => $schema->string()->required(),
        ];
    }
}
