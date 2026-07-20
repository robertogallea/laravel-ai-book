<?php

namespace App\Support\Eval;

use Closure;

/**
 * Runs an eval set against a live call to the agent under test, scoring
 * each case with its own criterion instead of a single shared equality
 * check. This is the mechanism, not a dataset: the categorization and
 * purchase-advisor eval commands each bring their own cases and their own
 * call to the agent they are gating.
 */
final class EvalRunner
{
    /**
     * @param  EvalCase[]  $cases
     * @param  Closure(string): array<string, mixed>  $call
     * @return string[] Names of the cases that failed their criterion.
     */
    public static function run(array $cases, Closure $call): array
    {
        $failed = [];

        foreach ($cases as $case) {
            $structured = $call($case->input);

            if (! ($case->criterion)($structured)) {
                $failed[] = $case->name;
            }
        }

        return $failed;
    }
}
