<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

/**
 * VectorRetriever
 *
 * Phase 2 Vector Search Implementation Placeholder.
 */
class VectorRetriever implements ResourceRetrieverInterface
{
    public function retrieve(string $query, int $projectId, int $limit = 5): Collection
    {
        // TODO: Phase 2 — implement vector search with embedding dimension
        return collect();
    }
}
