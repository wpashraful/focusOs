<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

interface ResourceRetrieverInterface
{
    /**
     * Retrieve matching document chunks based on a query text and project scope.
     *
     * @param  string  $query
     * @param  int     $projectId
     * @param  int     $limit
     * @return Collection         Collection of ResourceChunk models
     */
    public function retrieve(string $query, int $projectId, int $limit = 5): Collection;
}
