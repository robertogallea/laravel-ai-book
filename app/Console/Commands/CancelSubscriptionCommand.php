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
        $rawCost = $this->argument('monthly_cost');
        $rawDaysUnused = $this->argument('days_unused');

        if (! is_numeric($rawCost) || (float) $rawCost < 0) {
            $this->components->error("Monthly cost must be a non-negative number, got \"{$rawCost}\".");

            return Command::INVALID;
        }

        if (! ctype_digit((string) $rawDaysUnused)) {
            $this->components->error("Days unused must be a non-negative whole number, got \"{$rawDaysUnused}\".");

            return Command::INVALID;
        }

        $cost = (float) $rawCost;
        $daysUnused = (int) $rawDaysUnused;

        $action = new ProposedAction(
            type: 'cancel_subscription',
            summary: "Cancel the \"{$name}\" subscription",
            context: [
                'Monthly cost' => sprintf('%.2f dollars', $cost),
                'Days since last use' => $daysUnused,
            ],
        );

        return $this->submitForApproval($action, fn () => "Subscription \"{$name}\" has been cancelled.");
    }
}
