<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Mcp\Facades\Mcp;
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
     * The only capability this application grants itself from the
     * exchange-rate MCP server: this server can be queried for exchange
     * rates, nothing else, no matter what other tools it lists when asked.
     *
     * @var array<int, string>
     */
    private const GRANTED_MCP_TOOLS = ['get-exchange-rate-tool'];

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
     * Discovered from the connected server, not declared by hand: the
     * server could list other capabilities tomorrow, and this agent would
     * still only ever see the one named in GRANTED_MCP_TOOLS above.
     */
    public function tools(): iterable
    {
        return Mcp::client('exchange-rates')
            ->tools()
            ->only(self::GRANTED_MCP_TOOLS)
            ->all();
    }
}
