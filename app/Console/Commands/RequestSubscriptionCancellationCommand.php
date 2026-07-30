<?php

namespace App\Console\Commands;

use App\Ai\Agents\SubscriptionCancellationAssistant;
use App\Console\Commands\Concerns\DisclosesAiInteraction;
use App\Console\Commands\Concerns\ResolvesUserOption;
use App\Support\AuditLog;
use App\Support\ProposedAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;

use function Laravel\Prompts\confirm;

#[Signature('assistant:request-subscription-cancellation {instruction : Natural-language cancellation request, e.g. "Cancel my Streaming Plus subscription"} {--user= : Email address of the user making the request}')]
#[Description("Ask the assistant to cancel a subscription, gated by the framework's native tool approval instead of the hand-rolled approval flow")]
class RequestSubscriptionCancellationCommand extends Command
{
    use DisclosesAiInteraction;
    use ResolvesUserOption;

    /**
     * Execute the console command.
     *
     * Unlike CancelSubscriptionCommand (Chapter 5), the proposal here is not
     * built by application code from CLI arguments: it is the model itself,
     * mid conversation, that decides to call
     * App\Ai\Tools\CancelSubscriptionTool, and generation pauses before that
     * call executes. What this command adds is only the CLI side of showing
     * that pending approval and resuming the same conversation with the
     * user's decision, not the gate itself, which lives entirely inside the
     * framework's tool-calling loop. Because everything here runs inside a
     * single process, the same agent instance carries its conversation
     * forward on its own; a multi-request flow, a real chat UI for
     * instance, would instead persist $response->conversationId and call
     * ->continue($conversationId, as: $user) on a fresh agent instance for
     * the second turn.
     */
    public function handle(): int
    {
        $this->discloseAiInteraction();

        $user = $this->resolveUserOption();

        if ($user === false) {
            return Command::INVALID;
        }

        $instruction = $this->argument('instruction');

        $agent = (new SubscriptionCancellationAssistant($user))->forUser($user);
        $response = $agent->prompt($instruction);

        if (! $response->hasPendingApprovals()) {
            $this->line($response->text);

            return Command::SUCCESS;
        }

        $decisions = [];

        foreach ($response->pendingApprovals as $pending) {
            $this->components->info("Proposed action: {$pending->tool}");
            $this->line("  Reason: {$pending->reason}");

            foreach ($pending->arguments as $label => $value) {
                $this->line("  {$label}: {$value}");
            }

            if (! confirm(label: 'Approve this action?', default: false)) {
                $this->recordRejectedApproval($pending);
                $decisions[$pending->id] = Decision::reject('Declined by the user.');

                continue;
            }

            $decisions[$pending->id] = Decision::approve();
        }

        $resumed = $agent->prompt(Decisions::from($decisions));

        $this->line($resumed->text);

        return Command::SUCCESS;
    }

    /**
     * CancelSubscriptionTool::handle() only runs for an approved call, so it
     * only ever logs an "approved" outcome: a rejected pending approval
     * never reaches the tool at all. This command records the rejection
     * itself instead, keeping the same "every outcome gets an audit trail
     * entry" guarantee AuditLog already provides for the Chapter 5
     * mechanism.
     */
    private function recordRejectedApproval(object $pending): void
    {
        $action = new ProposedAction(
            type: 'cancel_subscription',
            summary: "Cancel via tool call: {$pending->tool}",
            context: $pending->arguments,
        );

        AuditLog::record($action, 'rejected');
    }
}
