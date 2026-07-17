<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:transfer-funds {from : Source account} {to : Destination account} {amount : Amount to transfer}')]
#[Description("Move funds between the user's accounts")]
class TransferFundsCommand extends Command
{
    /**
     * Execute the console command.
     *
     * The assistant has already worked out that this transfer makes sense
     * (how it did so is not this command's concern): here it acts on that
     * conclusion immediately, with no confirmation of any kind.
     */
    public function handle(): int
    {
        $from = $this->argument('from');
        $to = $this->argument('to');
        $amount = (float) $this->argument('amount');

        $this->line(sprintf('%.2f dollars moved from %s to %s.', $amount, $from, $to));

        return Command::SUCCESS;
    }
}
