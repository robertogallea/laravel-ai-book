<?php

namespace App\Support;

/**
 * An action the assistant has already formulated as a concrete proposal.
 * How it got to this form is out of scope here: this object only carries
 * what the approval flow needs to show the user, not how to run it. The
 * closure that actually performs the action is never attached to this
 * object: it is passed straight to RequiresApproval::submitForApproval(),
 * which is the only code in the application that ever invokes it, so
 * there is nowhere else an executable action can be reached from.
 */
final class ProposedAction
{
    /**
     * @param  array<string, string|int>  $context
     */
    public function __construct(
        public readonly string $type,
        public readonly string $summary,
        public readonly array $context,
    ) {}
}
