<?php

namespace App\Console\Commands\Concerns;

use App\Support\AuditLog;
use App\Support\ProposedAction;
use Closure;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\confirm;

/**
 * Submits a proposed action to the user and runs it only from inside the
 * branch reached after explicit approval: there is no other path to
 * execution anywhere in this trait, and the executor closure is never
 * exposed anywhere else for other code to call directly. A rejection
 * stops here for good, is recorded exactly like every other outcome, and
 * is never retried or reformulated into a second attempt at the same
 * action. Rejection, a failed execution, and an inability to even ask
 * for approval are three distinct outcomes, each recorded and reported
 * as such instead of being collapsed into a single generic failure.
 */
trait RequiresApproval
{
    private function submitForApproval(ProposedAction $action, Closure $executor): int
    {
        $this->components->info("Proposed action: {$action->summary}");

        foreach ($action->context as $label => $value) {
            $this->line("  {$label}: {$value}");
        }

        if (! $this->input->isInteractive()) {
            AuditLog::record($action, 'skipped', 'no interactive terminal available to ask for approval');
            $this->components->error('This action requires an explicit approval, but no interactive terminal is available to ask for one. Nothing was executed.');

            return Command::FAILURE;
        }

        if (! confirm(label: 'Approve this action?', default: false)) {
            AuditLog::record($action, 'rejected');
            $this->components->warn('Action rejected. Nothing was executed.');

            return Command::INVALID;
        }

        try {
            $result = $executor();
        } catch (Throwable $e) {
            AuditLog::record($action, 'failed', $e->getMessage());
            $this->components->error("Action approved but execution failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        AuditLog::record($action, 'approved', $result);
        $this->line($result);

        return Command::SUCCESS;
    }
}
