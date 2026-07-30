<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\AuditLog;
use App\Support\ProposedAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Cancels a named subscription, the same simulated, non-persisted
 * cancellation already used by App\Console\Commands\CancelSubscriptionCommand
 * (Chapter 5): config('finance.subscriptions') is never actually mutated,
 * only a confirmation string is returned and the outcome is logged.
 *
 * Unlike that command, this tool is called by the model itself, mid
 * conversation, when it decides cancellation is what the user asked for.
 * needsApproval() always requires a human decision before handle() ever
 * runs, so handle() only ever executes for a call the framework has
 * already let through: it logs an "approved" outcome unconditionally, the
 * rejected case never reaches it at all (see
 * App\Console\Commands\RequestSubscriptionCancellationCommand, which
 * records that outcome instead).
 */
class CancelSubscriptionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Cancel a named recurring subscription on behalf of the user.';
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Cancelling a subscription is an irreversible financial action.');
    }

    public function handle(Request $request): Stringable|string
    {
        $name = $request['subscription_name'];

        $subscription = (new Collection(config('finance.subscriptions')))
            ->firstWhere('name', $name);

        if ($subscription === null) {
            return "No subscription named \"{$name}\" was found.";
        }

        $action = new ProposedAction(
            type: 'cancel_subscription',
            summary: "Cancel the \"{$name}\" subscription",
            context: [
                'Monthly cost' => sprintf('%.2f dollars', $subscription['monthly_cost']),
                'Days since last use' => $subscription['days_unused'],
            ],
        );

        $result = "Subscription \"{$name}\" has been cancelled.";

        AuditLog::record($action, 'approved', $result);

        return $result;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subscription_name' => $schema->string()->required(),
        ];
    }
}
