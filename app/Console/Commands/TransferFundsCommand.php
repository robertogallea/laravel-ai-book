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
        $rawAmount = $this->argument('amount');

        if (! is_numeric($rawAmount) || (float) $rawAmount <= 0) {
            $this->components->error("Amount must be a positive number, got \"{$rawAmount}\".");

            return Command::INVALID;
        }

        if ($from === $to) {
            $this->components->error("Source and destination accounts must be different, both were \"{$from}\".");

            return Command::INVALID;
        }

        $amount = (float) $rawAmount;
        $formattedAmount = sprintf('%.2f dollars', $amount);

        $action = new ProposedAction(
            type: 'transfer_funds',
            summary: "Move {$formattedAmount} from {$from} to {$to}",
            context: [
                'Amount' => $formattedAmount,
                'From account' => $from,
                'To account' => $to,
            ],
        );

        return $this->submitForApproval($action, fn () => "{$formattedAmount} have been moved from {$from} to {$to}.");
    }
}
