<?php

namespace App\Support;

use Closure;

/**
 * An action the assistant has already formulated as a concrete proposal.
 * How it got to this form is out of scope here: this object only carries
 * what the approval flow needs to show the user and, if approved, run.
 */
final class ProposedAction
{
    /**
     * @param  array<string, string|int|float>  $context
     */
    public function __construct(
        public readonly string $type,
        public readonly string $summary,
        public readonly array $context,
        private readonly Closure $executor,
    ) {}

    /**
     * Run the action. Only ever called after explicit approval: see
     * App\Console\Commands\Concerns\RequiresApproval.
     */
    public function execute(): string
    {
        return ($this->executor)();
    }
}
