<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Records the outcome of a proposed action so it can be reconstructed
 * later without relying on anyone's memory of what happened. Every
 * outcome is recorded the same way, including a failed execution: an
 * audit trail that only ever showed approvals would not be one.
 */
class AuditLog
{
    /**
     * @param  'approved'|'rejected'|'failed'|'skipped'  $outcome
     */
    public static function record(ProposedAction $action, string $outcome, ?string $detail = null): void
    {
        Log::info("proposed_action.{$outcome}", [
            'type' => $action->type,
            'summary' => $action->summary,
            'context' => $action->context,
            'detail' => $detail,
        ]);
    }
}
