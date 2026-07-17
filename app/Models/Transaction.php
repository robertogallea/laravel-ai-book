<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'date',
        'embedding' => 'array',
    ];

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
