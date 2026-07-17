<?php

namespace App\Console\Commands\Concerns;

use App\Support\AuditLog;
use App\Support\ProposedAction;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Submits a proposed action to the user and runs it only from inside the
 * branch reached after explicit approval: there is no other path to
 * execution anywhere in this trait. A rejection stops here for good, is
 * recorded exactly like an approval would be, and is never retried or
 * reformulated into a second attempt at the same action.
 */
trait RequiresApproval
{
    private function submitForApproval(ProposedAction $action): int
    {
        $this->components->info("Proposed action: {$action->summary}");

        foreach ($action->context as $label => $value) {
            $this->line("  {$label}: {$value}");
        }

        if (! confirm(label: 'Approve this action?', default: false)) {
            AuditLog::record($action, approved: false);
            $this->components->warn('Action rejected. Nothing was executed.');

            return Command::FAILURE;
        }

        $result = $action->execute();

        AuditLog::record($action, approved: true);
        $this->line($result);

        return Command::SUCCESS;
    }
}
