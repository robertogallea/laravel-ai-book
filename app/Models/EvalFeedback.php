<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's negative rating on a categorization response, held for review
 * before it can become part of the categorization eval set. Raw feedback
 * alone never sets "expected_category": only a reviewer's confirmation,
 * through ReviewFeedbackCommand, does that.
 */
class EvalFeedback extends Model
{
    use HasFactory;

    /**
     * The three statuses a feedback row moves through, named once here
     * instead of as a string literal repeated across every command and
     * factory that reads or writes one.
     */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'eval_feedback';

    protected $guarded = [];
}
