<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetExchangeRateTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answers questions that involve converting an amount between currencies.
 * Exchange rates move daily: the instructions exist to stop the model from
 * answering out of a rate it happens to remember from training, instead of
 * retrieving the current one through the tool available to it.
 */
class CurrencyAdvisor implements Agent, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You help the user convert an amount from one currency to another.
        Exchange rates change daily: never answer from a rate you already
        know, always retrieve the current one with the tool available to
        you before answering.

        Give the converted amount first, then the rate you used to compute
        it, in one or two short sentences.
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
            new GetExchangeRateTool,
        ];
    }
}
