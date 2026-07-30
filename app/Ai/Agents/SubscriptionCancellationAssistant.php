<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CancelSubscriptionTool;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * A narrowly scoped conversational agent whose only real capability is
 * cancelling a subscription on the user's request. It exists separately
 * from PurchaseAdvisor (earlier in this chapter) on purpose: native tool
 * approval needs a Conversational agent with persisted history to pause and
 * resume across the approval decision, and PurchaseAdvisor is deliberately
 * not that, a single-shot structured-output agent whose eval set (Chapter
 * 11) depends on its current shape.
 */
class SubscriptionCancellationAssistant implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(private readonly User $user) {}

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
        You help the user cancel one of their recurring subscriptions. When
        they ask to cancel a specific subscription, call the tool available
        to you with that subscription's exact name. Do not cancel anything
        the user did not explicitly ask to cancel.
        TEXT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new CancelSubscriptionTool($this->user),
        ];
    }
}
