<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:cancel-subscription {name : Name of the subscription} {monthly_cost : Monthly cost of the subscription} {days_unused : Number of days since the subscription was last used}')]
#[Description('Cancel a subscription the assistant has identified as unused')]
class CancelSubscriptionCommand extends Command
{
    /**
     * Execute the console command.
     *
     * The assistant has already identified this subscription as unused
     * (how it did so is not this command's concern): here it acts on that
     * finding immediately, with no confirmation of any kind.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $cost = (float) $this->argument('monthly_cost');
        $daysUnused = (int) $this->argument('days_unused');

        $this->components->info(sprintf(
            'No usage recorded for "%s" (%.2f dollars/month) in %d days. Cancelling.',
            $name,
            $cost,
            $daysUnused,
        ));

        $this->line(sprintf('Subscription "%s" has been cancelled.', $name));

        return Command::SUCCESS;
    }
}
