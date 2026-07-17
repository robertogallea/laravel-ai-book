<?php

namespace Tests\Unit;

use App\Support\VectorStore;
use PHPUnit\Framework\TestCase;

class VectorStoreTest extends TestCase
{
    public function test_it_ranks_items_by_similarity_to_the_query_embedding_most_similar_first(): void
    {
        $aligned = self::item([1.0, 0.0]);
        $opposite = self::item([-1.0, 0.0]);
        $orthogonal = self::item([0.0, 1.0]);

        $result = VectorStore::nearest([1.0, 0.0], [$opposite, $orthogonal, $aligned], limit: 3);

        $this->assertSame([$aligned, $orthogonal, $opposite], $result);
    }

    public function test_it_keeps_only_the_top_limit_items(): void
    {
        $items = [
            self::item([1.0, 0.0]),
            self::item([0.9, 0.1]),
            self::item([0.1, 0.9]),
        ];

        $result = VectorStore::nearest([1.0, 0.0], $items, limit: 2);

        $this->assertCount(2, $result);
        $this->assertSame([$items[0], $items[1]], $result);
    }

    public function test_similarity_is_insensitive_to_vector_magnitude(): void
    {
        $shortVector = self::item([1.0, 0.0]);
        $longVector = self::item([10.0, 0.0]);

        $result = VectorStore::nearest([1.0, 0.0], [$shortVector, $longVector], limit: 2);

        // Both point in exactly the same direction as the query, only their
        // magnitude differs: cosine similarity must treat them as equally
        // relevant, since it measures direction, not length.
        $this->assertCount(2, $result);
    }

    /**
     * @param  array<float>  $embedding
     */
    private static function item(array $embedding): object
    {
        return (object) ['embedding' => $embedding];
    }
}
