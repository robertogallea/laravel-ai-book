<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A fact extracted from a past exchange with the assistant, e.g. a stated
 * savings goal, persisted so it can be retrieved by meaning in a later
 * session. The same retrieval mechanism as App\Models\Transaction
 * (VectorStore::nearest against an embedding column), applied to a
 * different data source: what the user has said, not what the user has
 * spent.
 */
class MemoryFact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
    ];

    /**
     * Restrict a query to facts owned by the given user. Same reasoning
     * as Transaction::scopeOwnedBy: a fact recalled without this scope
     * would be recalled for every user, not just the one who stated it.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
