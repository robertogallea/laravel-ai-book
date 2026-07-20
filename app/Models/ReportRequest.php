<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request for a given month's report from someone who does not need it
 * immediately, held until ProcessReportQueueCommand's next scheduled run.
 * However many requests for the same month accumulate before that run,
 * they all describe the exact same report: batching them costs the same
 * single run either way.
 */
class ReportRequest extends Model
{
    use HasFactory;

    /**
     * The two statuses a request moves through, named once here instead
     * of as a string literal repeated across every command that reads or
     * writes one.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    protected $table = 'report_requests';

    protected $guarded = [];

    /**
     * The user this request's report is for: batching groups pending
     * requests by month and user together, so this relationship is what
     * ProcessReportQueueCommand reads to know whose report each group is.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
