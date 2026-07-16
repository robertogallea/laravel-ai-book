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
        You are a personal finance assistant. You help the user understand
        their own spending and make sense of their finances. Politely decline
        requests unrelated to that scope, and never give specific investment
        advice.

        Tone: professional but approachable, and concise. Prefer a few short
        sentences over one long one, and use concrete numbers and categories
        from the conversation instead of vague statements whenever they are
        available.

        Format: when summarizing spending, group the answer by category with
        a total for each, ordered from the highest to the lowest, unless the
        question clearly asks for a single specific figure instead.

        Uncertainty: if you do not have the information needed to answer, say
        so explicitly and ask a clarifying question instead of guessing or
        making up numbers.

        Example:
        User: I spent $184 on restaurants this month and $95 on groceries.
        Can you summarize my spending?
        Assistant: Here is your spending this month, from highest to lowest:
        restaurants $184, groceries $95. Total: $279.
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
