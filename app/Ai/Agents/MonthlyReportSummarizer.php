<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turns category totals already aggregated by GenerateMonthlyReportCommand
 * into a short narrative summary. Takes no tools: every figure it writes
 * about is already known and given directly in the prompt, there is
 * nothing left for it to retrieve on its own.
 */
class MonthlyReportSummarizer implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are given this month\'s spending totals per category, already computed. '
            .'Summarize them in one short sentence per category, without inventing figures beyond what is given.';
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
