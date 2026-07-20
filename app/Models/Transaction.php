<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The cache key every spending answer (see AskSpendingCommand) is
     * scoped to: a single shared constant, not a string literal repeated
     * in both files, so the two can never silently drift apart.
     */
    public const SPENDING_ANSWERS_CACHE_VERSION_KEY = 'spending_answers_cache_version';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'date',
        'embedding' => 'array',
    ];

    /**
     * Every cached spending answer is keyed on this version, not on this
     * transaction alone: a transaction being created, or an existing one
     * being modified, for instance IndexTransactionsCommand giving it an
     * embedding after the fact, can change the correct answer to a
     * question asked before that change, so bumping it here makes every
     * answer cached under the previous version unreachable, without
     * tracking which specific questions it might affect.
     */
    protected static function booted(): void
    {
        static::created(fn () => static::bumpSpendingAnswersCacheVersion());
        static::updated(fn () => static::bumpSpendingAnswersCacheVersion());
    }

    /**
     * A plain read-then-write, not Cache::increment(): this app's
     * configured default cache store (database) does not create a
     * missing key on increment, only array/redis-like stores do, so
     * relying on increment alone would silently never bump this version
     * under the store this app actually runs on. Not perfectly atomic
     * under concurrent writes, an accepted trade in exchange for behaving
     * the same way on every cache store instead of only some. Shared
     * across every user on purpose, not scoped per user: a transaction
     * belonging to one user still invalidates every other user's cached
     * spending answers too, a safe but wasteful over-invalidation rather
     * than a correctness gap, and a cheaper failure mode than the
     * opposite mistake of a cached answer nobody ever invalidates.
     */
    private static function bumpSpendingAnswersCacheVersion(): void
    {
        Cache::forever(
            self::SPENDING_ANSWERS_CACHE_VERSION_KEY,
            (int) Cache::get(self::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0) + 1,
        );
    }

    /**
     * Restrict a query to transactions owned by the given user. The single
     * named call every user-facing read of this model's data is expected
     * to go through, instead of each one repeating its own where('user_id',
     * ...) and risking one that forgets to.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
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
