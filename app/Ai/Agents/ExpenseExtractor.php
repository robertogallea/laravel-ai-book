<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class ExpenseExtractor implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You extract expense details from a short description of a single
        purchase. Restate the amount, category, and date as one plain
        sentence, in that order, with no other commentary. Always give the
        amount as a number followed by the word "dollars", and the date in
        YYYY-MM-DD format.
        TEXT;
    }
}
