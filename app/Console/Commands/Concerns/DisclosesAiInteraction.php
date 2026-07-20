<?php

namespace App\Console\Commands\Concerns;

/**
 * Every command that puts the user in direct conversation with an agent,
 * one-off or ongoing, shows this before anything else happens: the same
 * one-line disclosure, so a user never reaches a first response without
 * having first been told what they are talking to.
 */
trait DisclosesAiInteraction
{
    private function discloseAiInteraction(): void
    {
        $this->components->info('You are talking to an automated AI assistant, not a human.');
    }
}
