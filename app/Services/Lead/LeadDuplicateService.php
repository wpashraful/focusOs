<?php

namespace App\Services\Lead;

use App\Models\Lead;

class LeadDuplicateService
{
    /**
     * Check if a lead already exists in a given workspace.
     */
    public function isDuplicate(string $name, ?string $website, ?string $phone, int $workspaceId): bool
    {
        // 1. Check duplicate by exact name in the workspace
        if (Lead::where('workspace_id', $workspaceId)->where('name', trim($name))->exists()) {
            return true;
        }

        // 2. Check duplicate by website domain in the workspace
        if ($website) {
            $domain = parse_url($website, PHP_URL_HOST) ?: $website;
            $domain = preg_replace('/^www\./', '', strtolower(trim($domain)));
            
            if (!empty($domain)) {
                $duplicateExists = Lead::where('workspace_id', $workspaceId)
                    ->whereNotNull('website')
                    ->where('website', 'like', '%' . $domain . '%')
                    ->exists();
                
                if ($duplicateExists) {
                    return true;
                }
            }
        }

        // 3. Check duplicate by phone number in the workspace
        if ($phone) {
            $phone = ltrim(trim($phone), '+');
            if (!empty($phone)) {
                if (Lead::where('workspace_id', $workspaceId)->where('phone', $phone)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
