<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Records the outcome of a proposed action, approved or rejected, so it can
 * be reconstructed later without relying on anyone's memory of what
 * happened. Both outcomes are recorded the same way: an audit trail that
 * only ever showed approvals would not be one.
 */
class AuditLog
{
    public static function record(ProposedAction $action, bool $approved): void
    {
        Log::info('proposed_action.'.($approved ? 'approved' : 'rejected'), [
            'type' => $action->type,
            'summary' => $action->summary,
            'context' => $action->context,
        ]);
    }
}
