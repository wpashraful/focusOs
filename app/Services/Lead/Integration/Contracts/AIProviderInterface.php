<?php

namespace App\Services\Lead\Integration\Contracts;

interface AIProviderInterface
{
    /**
     * Audit a lead using business context.
     * Must return array containing: strengths, gaps, suggestions, cold_email_pitch
     */
    public function audit(array $leadData, array $config): array;
}
