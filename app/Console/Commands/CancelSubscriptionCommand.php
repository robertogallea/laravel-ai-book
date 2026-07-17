<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RequiresApproval;
use App\Support\ProposedAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:cancel-subscription {name : Name of the subscription} {monthly_cost : Monthly cost of the subscription} {days_unused : Number of days since the subscription was last used}')]
#[Description('Propose cancelling a subscription the assistant has identified as unused, pending explicit approval')]
class CancelSubscriptionCommand extends Command
{
    use RequiresApproval;

    /**
     * Execute the console command.
     *
     * The assistant has already identified this subscription as unused (how
     * is not this command's concern): here that finding only becomes a
     * proposal, submitted for explicit approval before anything is cancelled.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $cost = (float) $this->argument('monthly_cost');
        $daysUnused = (int) $this->argument('days_unused');

        $action = new ProposedAction(
            type: 'cancel_subscription',
            summary: "Cancel the \"{$name}\" subscription",
            context: [
                'Monthly cost' => sprintf('%.2f dollars', $cost),
                'Days since last use' => $daysUnused,
            ],
            executor: fn () => "Subscription \"{$name}\" has been cancelled.",
        );

        return $this->submitForApproval($action);
    }
}
