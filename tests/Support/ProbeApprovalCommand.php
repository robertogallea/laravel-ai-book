<?php

namespace Tests\Support;

use App\Console\Commands\Concerns\RequiresApproval;
use App\Support\ProposedAction;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A minimal command that submits a proposed action whose executor always
 * throws, registered ad hoc by tests that need to exercise
 * RequiresApproval's failure path in isolation. The two real commands
 * (CancelSubscriptionCommand, TransferFundsCommand) have no failure mode
 * of their own to trigger this with, so this probe exists purely to test
 * the shared trait's behavior when execution fails after approval.
 */
#[Signature('test:probe-approval')]
class ProbeApprovalCommand extends Command
{
    use RequiresApproval;

    public function handle(): int
    {
        $action = new ProposedAction(
            type: 'probe_action',
            summary: 'Probe action',
            context: ['Detail' => 'probe'],
        );

        return $this->submitForApproval($action, function () {
            throw new \RuntimeException('simulated execution failure');
        });
    }
}
