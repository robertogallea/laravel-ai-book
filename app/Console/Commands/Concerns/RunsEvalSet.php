<?php

namespace App\Console\Commands\Concerns;

use App\Support\Eval\EvalCase;
use App\Support\Eval\EvalRunner;
use Closure;
use Illuminate\Console\Command;

/**
 * The reporting half of every eval command in this chapter: run a set of
 * cases through EvalRunner, print how many passed, and gate on a full
 * pass. Every eval command brings its own cases and its own call to the
 * agent it is gating; this is the one part identical across all of them.
 */
trait RunsEvalSet
{
    /**
     * @param  EvalCase[]  $cases
     * @param  Closure(string): array<string, mixed>  $call
     */
    private function runEvalSet(string $label, array $cases, Closure $call): int
    {
        $failed = EvalRunner::run($cases, $call);

        $total = count($cases);
        $passed = $total - count($failed);

        $this->line("{$label} eval: {$passed}/{$total} cases passed.");

        if ($failed !== []) {
            $this->components->error('Failed cases: '.implode(', ', $failed));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
