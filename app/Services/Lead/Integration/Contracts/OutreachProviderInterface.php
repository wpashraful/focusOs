<?php

namespace App\Services\Lead\Integration\Contracts;

interface OutreachProviderInterface
{
    /**
     * Send email outreach.
     */
    public function send(array $emailData, array $config): bool;
}
