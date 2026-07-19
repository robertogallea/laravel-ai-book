<?php

namespace App\Console\Commands\Concerns;

/**
 * A schema declaring a field required does not guarantee the model's
 * response actually contains it: every call site that reads one must
 * check it, not just trust the schema on the model's behalf.
 */
trait ReadsStructuredResponse
{
    /**
     * Read a field from a structured response, returning it only if it is
     * a non-empty string, null otherwise.
     *
     * @param  array<string, mixed>  $structured
     */
    private function stringField(array $structured, string $key): ?string
    {
        $value = $structured[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
