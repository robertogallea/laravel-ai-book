<?php

namespace App\Models;

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
}
