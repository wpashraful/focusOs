<?php

namespace App\Services\Lead\Integration\Contracts;

interface SheetsProviderInterface
{
    /**
     * Sync a lead record to a spreadsheet.
     */
    public function sync(array $leadData, array $config): bool;
}
