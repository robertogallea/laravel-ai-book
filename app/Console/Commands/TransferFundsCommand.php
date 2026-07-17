<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresApproval;
use App\Support\ProposedAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:transfer-funds {from : Source account} {to : Destination account} {amount : Amount to transfer}')]
#[Description("Propose moving funds between the user's accounts, pending explicit approval")]
class TransferFundsCommand extends Command
{
    use RequiresApproval;

    /**
     * Execute the console command.
     *
     * The assistant has already worked out that this transfer makes sense
     * (how is not this command's concern): here that conclusion only
     * becomes a proposal, submitted for explicit approval before any funds
     * actually move.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $to = $this->argument('to');
        $amount = (float) $this->argument('amount');

        $action = new ProposedAction(
            type: 'transfer_funds',
            summary: sprintf('Move %.2f dollars from %s to %s', $amount, $from, $to),
            context: [
                'Amount' => sprintf('%.2f dollars', $amount),
                'From account' => $from,
                'To account' => $to,
            ],
            executor: fn () => sprintf('%.2f dollars have been moved from %s to %s.', $amount, $from, $to),
        );

        return $this->submitForApproval($action);
    }
}
