<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Given a single message sent to the assistant, decides whether it states
 * something worth remembering across future sessions, a goal, preference,
 * or commitment, as opposed to a routine question about existing data. A
 * routine question yields no fact at all: not every message is worth
 * persisting.
 */
class FactExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Decide whether the given message states a fact worth remembering across future '
            .'sessions: a stated goal, preference, or commitment. If it does, extract it as a '
            .'short, self-contained statement. If it is only a question or does not state '
            .'anything worth remembering, return null instead.';
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'fact' => $schema->string()->nullable(),
        ];
    }
}
