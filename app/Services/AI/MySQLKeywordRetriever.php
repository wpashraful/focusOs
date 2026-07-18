<?php

namespace App\Services\AI;

use App\Models\ResourceChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MySQLKeywordRetriever implements ResourceRetrieverInterface
{
    public function retrieve(string $query, int $projectId, int $limit = 5): Collection
    {
        $sanitizedQuery = trim(preg_replace('/[+\-><\(\)~*\"@]+/u', ' ', $query));

        if (empty($sanitizedQuery)) {
            return collect();
        }

        // 1. Attempt FULLTEXT search first
        try {
            $chunks = ResourceChunk::whereHas('resource', function ($q) use ($projectId) {
                $q->where('project_id', $projectId)->where('status', 'ready');
            })
            ->whereRaw(
                "MATCH(content, keyword_tags) AGAINST(? IN NATURAL LANGUAGE MODE)",
                [$sanitizedQuery]
            )
            ->take($limit)
            ->get();

            if ($chunks->isNotEmpty()) {
                return $chunks;
            }
        } catch (\Exception $e) {
            // Log fulltext exception and fall back to LIKE
        }

        // 2. LIKE fallback if FULLTEXT yielded nothing
        $terms = explode(' ', $sanitizedQuery);
        $dbQuery = ResourceChunk::whereHas('resource', function ($q) use ($projectId) {
            $q->where('project_id', $projectId)->where('status', 'ready');
        });

        foreach ($terms as $term) {
            if (strlen($term) > 2) {
                $dbQuery->where(function($q) use ($term) {
                    $q->where('content', 'like', "%{$term}%")
                      ->orWhere('keyword_tags', 'like', "%{$term}%");
                });
            }
        }

        return $dbQuery->take($limit)->get();
    }
}
