<?php

namespace App\Services\Lead\Integration\Contracts;

interface SearchProviderInterface
{
    /**
     * Search lead sources.
     */
    public function search(string $query, array $config): array;
}
