<?php

namespace App\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A minimal vector store for the example app: rather than a dedicated
 * vector database, it ranks a small, already-loaded set of items by cosine
 * similarity to a query embedding. This does not scale past a few thousand
 * items, but keeps the concept the book cares about, ranking by embedding
 * similarity instead of exact matches, visible without pulling in an
 * external dependency the example does not otherwise need.
 */
class VectorStore
{
    /**
     * Return up to `$limit` items whose embedding is closest to the query
     * embedding, most similar first, keeping only those at or above
     * `$minSimilarity`. A limit alone only bounds how many items come back,
     * not whether any of them are actually relevant: without this floor, a
     * query unrelated to anything indexed would still receive whatever
     * ranks highest, presented as if it were relevant.
     *
     * @param  array<float>  $queryEmbedding
     * @param  iterable<object{embedding: array<float>}>  $items
     * @return array<object{embedding: array<float>}>
     */
    public static function nearest(array $queryEmbedding, iterable $items, int $limit, float $minSimilarity): array
    {
        return (new Collection($items))
            ->map(fn ($item) => [$item, self::cosineSimilarity($queryEmbedding, $item->embedding)])
            ->filter(fn (array $ranked) => $ranked[1] >= $minSimilarity)
            ->sortByDesc(fn (array $ranked) => $ranked[1])
            ->take($limit)
            ->map(fn (array $ranked) => $ranked[0])
            ->values()
            ->all();
    }

    /**
     * Cosine similarity between two embeddings: the cosine of the angle
     * between them, a value insensitive to their magnitude. Public so the
     * property it guarantees can be exercised directly, independent of
     * ranking or filtering.
     *
     * A zero-magnitude embedding (every component zero) has no direction to
     * compare, so it is treated as having zero similarity to anything
     * rather than dividing by zero.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     *
     * @throws InvalidArgumentException if the two embeddings do not have the same number of dimensions.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot compare embeddings of different dimensions (%d and %d): they were likely computed by different embedding models.',
                count($a),
                count($b),
            ));
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dotProduct += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
