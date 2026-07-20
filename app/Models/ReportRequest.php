<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request for this month's report from someone who does not need it
 * immediately, held until ProcessReportQueueCommand's next scheduled run.
 * However many of these accumulate before that run, they all describe the
 * exact same report: batching them costs the same single run either way.
 */
class ReportRequest extends Model
{
    protected $table = 'report_requests';

    protected $guarded = [];
}
