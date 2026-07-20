<?php

namespace App\Support\Eval;

use Closure;

/**
 * One case in an eval set: an input to send to the agent under test, and a
 * criterion that judges whether the structured response it produced is
 * correct. The criterion is a closure rather than a fixed expected value
 * because not every case reduces to a single equality check: a closed-set
 * classification case can compare one field, but a multi-step agent's case
 * needs several conditions checked together to call a response correct.
 */
final class EvalCase
{
    /**
     * @param  Closure(array<string, mixed>): bool  $criterion
     */
    public function __construct(
        public readonly string $name,
        public readonly string $input,
        public readonly Closure $criterion,
    ) {}
}
