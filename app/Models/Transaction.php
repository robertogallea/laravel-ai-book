<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'date',
        'embedding' => 'array',
    ];

    /**
     * Every cached spending answer (see AskSpendingCommand) is keyed on
     * this version, not on this transaction alone: a new transaction can
     * change the correct answer to a question that was asked before it
     * existed, so incrementing it here makes every answer cached under
     * the previous version unreachable, without tracking which specific
     * questions it might affect.
     */
    protected static function booted(): void
    {
        static::created(fn () => Cache::increment('spending_answers_cache_version'));
    }

    /**
     * The text representation this transaction is embedded from, and shown
     * to the assistant as retrieved context: dense enough to answer
     * questions about merchant, category, amount, and date.
     */
    public function description(): string
    {
        return sprintf(
            '%s: $%.2f at %s on %s',
            ucfirst($this->category),
            $this->amount,
            $this->merchant,
            $this->occurred_at->toDateString(),
        );
    }
}
