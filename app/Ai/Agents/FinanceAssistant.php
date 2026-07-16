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
        You are a helpful personal finance assistant. You answer questions
        about the user's spending clearly and concisely, referring back to
        amounts and categories the user has already mentioned earlier in
        the conversation when relevant.
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
