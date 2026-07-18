<?php

namespace App\Console\Commands;

use App\Ai\Agents\PurchaseAdvisor;
use App\Console\Commands\Concerns\RequiresApproval;
use App\Support\ProposedAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:assess-purchase {amount : Cost of the purchase being considered} {description : Short description of what is being purchased}')]
#[Description('Ask the assistant whether a purchase is affordable, grounded in the real account data')]
class AssessPurchaseCommand extends Command
{
    use RequiresApproval;

    /**
     * Execute the console command.
     *
     * The assistant reaches its verdict by consulting the account balance,
     * recurring subscriptions, and budget status itself, deciding along
     * the way which of those to check and in what order (see
     * App\Ai\Agents\PurchaseAdvisor). If its conclusion includes
     * cancelling an unused subscription to free up budget, that
     * suggestion becomes a proposal submitted through the very same
     * approval gate every other consequential action in this application
     * goes through: the assistant never cancels anything on its own.
     */
    public function handle(): int
    {
        $rawAmount = $this->argument('amount');
        $description = $this->argument('description');

        if (! is_numeric($rawAmount) || (float) $rawAmount <= 0) {
            $this->components->error("Amount must be a positive number, got \"{$rawAmount}\".");

            return Command::INVALID;
        }

        $amount = (float) $rawAmount;

        $goal = sprintf('Can the user afford to spend %.2f dollars on %s?', $amount, $description);

        $response = (new PurchaseAdvisor)->prompt($goal);

        $affordable = $response->structured['affordable'] ?? null;
        $reasoning = $response->structured['reasoning'] ?? null;
        $suggestedAction = $response->structured['suggested_action'] ?? null;

        // The schema declares "affordable" and "reasoning" required, but
        // that does not guarantee the model's response actually contains
        // them (see the chapter on structured output): trust nothing that
        // was not actually checked, including the shape of an optional
        // suggested action if one is present at all.
        if (! is_bool($affordable) || ! is_string($reasoning) || $reasoning === '' || ! $this->suggestedActionIsWellFormed($suggestedAction)) {
            $this->components->error('The assistant returned an incomplete assessment. Please try again in a moment.');

            return Command::FAILURE;
        }

        $this->line($affordable ? 'Affordable: yes.' : 'Affordable: no.');
        $this->line($reasoning);

        if ($suggestedAction === null) {
            return Command::SUCCESS;
        }

        $action = new ProposedAction(
            type: 'cancel_subscription',
            summary: "Cancel the \"{$suggestedAction['subscription_name']}\" subscription to free up budget",
            context: [
                'Monthly cost' => sprintf('%.2f dollars', $suggestedAction['monthly_cost']),
                'Days since last use' => $suggestedAction['days_unused'],
            ],
        );

        return $this->submitForApproval(
            $action,
            fn () => "Subscription \"{$suggestedAction['subscription_name']}\" has been cancelled.",
        );
    }

    private function suggestedActionIsWellFormed(mixed $suggestedAction): bool
    {
        if ($suggestedAction === null) {
            return true;
        }

        return is_array($suggestedAction)
            && isset($suggestedAction['subscription_name'], $suggestedAction['monthly_cost'], $suggestedAction['days_unused'])
            && is_string($suggestedAction['subscription_name'])
            && is_numeric($suggestedAction['monthly_cost'])
            && is_int($suggestedAction['days_unused']);
    }
}
