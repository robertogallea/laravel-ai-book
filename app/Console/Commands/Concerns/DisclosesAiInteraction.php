<?php

namespace App\Console\Commands\Concerns;

/**
 * Every command that sends what the user just typed to an agent and
 * prints its response directly back to them, one-off or ongoing, shows
 * this before anything else happens: the same one-line disclosure, so a
 * user never reaches a first response without having first been told
 * what they are talking to. A command that only runs a fixed evaluation
 * case, or generates a report for later delivery rather than answering
 * the user in the moment, has no such moment to disclose at and does not
 * use this trait.
 */
trait DisclosesAiInteraction
{
    private function discloseAiInteraction(): void
    {
        $this->components->info('You are talking to an automated AI assistant, not a human.');
    }
}
