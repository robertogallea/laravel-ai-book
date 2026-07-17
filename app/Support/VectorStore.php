<?php

namespace App\Support;

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
     * Return the `$limit` items whose embedding is closest to the query
     * embedding, most similar first.
     *
     * @param  array<float>  $queryEmbedding
     * @param  iterable<object{embedding: array<float>}>  $items
     * @return array<object{embedding: array<float>}>
     */
    public static function nearest(array $queryEmbedding, iterable $items, int $limit): array
    {
        $ranked = [];

        foreach ($items as $item) {
            $ranked[] = [$item, self::cosineSimilarity($queryEmbedding, $item->embedding)];
        }

        usort($ranked, fn (array $a, array $b) => $b[1] <=> $a[1]);

        return array_column(array_slice($ranked, 0, $limit), 0);
    }

    /**
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dotProduct += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
