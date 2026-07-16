<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class FinanceAssistant implements Agent, Conversational
{
    use Promptable;

    /** @var Message[] */
    protected array $history = [];

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You are an assistant that helps with finances.
        TEXT;
    }

    /**
     * Provide the conversation history the agent should see for its next reply.
     *
     * @param  Message[]  $history
     */
    public function withHistory(array $history): static
    {
        $this->history = $history;

        return $this;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }
}
