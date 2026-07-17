<?php

namespace Tests\Unit;

use App\Support\VectorStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VectorStoreTest extends TestCase
{
    public function test_it_ranks_items_by_similarity_to_the_query_embedding_most_similar_first(): void
    {
        $aligned = self::item([1.0, 0.0]);
        $opposite = self::item([-1.0, 0.0]);
        $orthogonal = self::item([0.0, 1.0]);

        // A threshold of -1.0 accepts every possible cosine similarity, so
        // nothing is filtered out and the ranking itself is what's tested.
        $result = VectorStore::nearest([1.0, 0.0], [$opposite, $orthogonal, $aligned], limit: 3, minSimilarity: -1.0);

        $this->assertSame([$aligned, $orthogonal, $opposite], $result);
    }

    public function test_it_keeps_only_the_top_limit_items(): void
    {
        $items = [
            self::item([1.0, 0.0]),
            self::item([0.9, 0.1]),
            self::item([0.1, 0.9]),
        ];

        $result = VectorStore::nearest([1.0, 0.0], $items, limit: 2, minSimilarity: 0.0);

        $this->assertCount(2, $result);
        $this->assertSame([$items[0], $items[1]], $result);
    }

    public function test_it_filters_out_items_below_the_minimum_similarity(): void
    {
        $relevant = self::item([1.0, 0.0]);
        $irrelevant = self::item([0.0, 1.0]);

        // The irrelevant item has a cosine similarity of 0.0 to the query,
        // below the 0.5 floor: a high limit must not let it through anyway.
        $result = VectorStore::nearest([1.0, 0.0], [$relevant, $irrelevant], limit: 5, minSimilarity: 0.5);

        $this->assertSame([$relevant], $result);
    }

    public function test_it_returns_nothing_when_no_item_clears_the_threshold(): void
    {
        $items = [self::item([0.0, 1.0]), self::item([-1.0, 0.0])];

        $result = VectorStore::nearest([1.0, 0.0], $items, limit: 5, minSimilarity: 0.5);

        $this->assertSame([], $result);
    }

    public function test_similarity_is_insensitive_to_vector_magnitude(): void
    {
        $shortVectorSimilarity = VectorStore::cosineSimilarity([1.0, 0.0], [1.0, 0.0]);
        $longVectorSimilarity = VectorStore::cosineSimilarity([1.0, 0.0], [10.0, 0.0]);

        // Both point in exactly the same direction as the query, only their
        // magnitude differs: cosine similarity must treat them identically,
        // since it measures direction, not length. A raw dot product
        // (magnitude-sensitive) would fail this assertion.
        $this->assertEquals(1.0, $shortVectorSimilarity);
        $this->assertEquals($shortVectorSimilarity, $longVectorSimilarity);
    }

    public function test_cosine_similarity_is_zero_for_a_zero_magnitude_embedding(): void
    {
        $this->assertSame(0.0, VectorStore::cosineSimilarity([1.0, 0.0], [0.0, 0.0]));
        $this->assertSame(0.0, VectorStore::cosineSimilarity([0.0, 0.0], [1.0, 0.0]));
        $this->assertSame(0.0, VectorStore::cosineSimilarity([0.0, 0.0], [0.0, 0.0]));
    }

    public function test_cosine_similarity_rejects_embeddings_of_different_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VectorStore::cosineSimilarity([1.0, 0.0], [1.0, 0.0, 0.0]);
    }

    /**
     * @param  array<float>  $embedding
     */
    private static function item(array $embedding): object
    {
        return (object) ['embedding' => $embedding];
    }
}
