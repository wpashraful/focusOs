<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\IntegrationSetting;
use App\Models\ImportSession;
use App\Jobs\Lead\EnrichLeadJob;
use App\Jobs\Lead\DraftLeadEmailJob;
use App\Jobs\Lead\SyncLeadToSheetsJob;
use App\Services\Lead\LeadDuplicateService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LeadImportService
{
    protected $duplicateService;

    public function __construct(LeadDuplicateService $duplicateService)
    {
        $this->duplicateService = $duplicateService;
    }

    /**
     * Import a collection of raw lead data.
     */
    public function importCollection(array $listings, int $workspaceId, ?int $projectId = null, ?int $importSessionId = null): array
    {
        $addedCount = 0;
        $duplicateCount = 0;
        $processedLeads = [];

        // Auto-provision ad-hoc session if none is supplied (backward compatibility)
        if (!$importSessionId) {
            $session = ImportSession::create([
                'workspace_id' => $workspaceId,
                'project_id' => $projectId,
                'status' => 'completed',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            $importSessionId = $session->id;
        } else {
            $session = ImportSession::find($importSessionId);
        }

        // Check if auto-enrichment is configured (default true)
        $autoEnrichSetting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('key', 'auto_enrich')
            ->first();
        $autoEnrich = $autoEnrichSetting ? filter_var($autoEnrichSetting->value, FILTER_VALIDATE_BOOLEAN) : true;

        foreach ($listings as $item) {
            if (empty($item['name'])) {
                continue;
            }

            // 1. Clean Inputs
            $name = trim($item['name']);
            $website = !empty($item['website']) ? trim($item['website']) : null;
            $phone = !empty($item['phone']) ? trim($item['phone']) : null;
            $email = !empty($item['email']) ? trim($item['email']) : null;
            $address = !empty($item['address']) ? trim($item['address']) : null;
            $rating = isset($item['rating']) ? (double) $item['rating'] : null;
            $reviewsCount = isset($item['reviews_count']) ? (int) $item['reviews_count'] : 0;

            if ($phone) {
                $phone = ltrim($phone, '+');
            }

            // 2. Duplicate Detection
            $isDuplicate = $this->duplicateService->isDuplicate($name, $website, $phone, $workspaceId);

            if ($isDuplicate) {
                // Save lead but skip enrichment pipeline
                $lead = Lead::create([
                    'workspace_id' => $workspaceId,
                    'project_id' => $projectId,
                    'import_session_id' => $importSessionId,
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'website' => $website,
                    'phone' => $phone,
                    'email' => $email,
                    'rating' => $rating,
                    'reviews_count' => $reviewsCount,
                    'address' => $address,
                    'status' => 'Lost', // CRM state for duplicates/ignored
                    'source' => $item['source'] ?? 'Google Maps',
                ]);

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'activity_type' => 'imported',
                    'description' => 'Lead imported and marked as duplicate (Ignored).',
                ]);

                $duplicateCount++;
            } else {
                // Save lead and transition to 'Imported' CRM state
                $lead = Lead::create([
                    'workspace_id' => $workspaceId,
                    'project_id' => $projectId,
                    'import_session_id' => $importSessionId,
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'website' => $website,
                    'phone' => $phone,
                    'email' => $email,
                    'rating' => $rating,
                    'reviews_count' => $reviewsCount,
                    'address' => $address,
                    'status' => 'Imported',
                    'source' => $item['source'] ?? 'Google Maps',
                ]);

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'activity_type' => 'imported',
                    'description' => 'Lead imported successfully.',
                ]);

                // 3. Dispatch Background Job Chain (Import -> Enrichment -> Email Draft -> Sync Sheets)
                if ($autoEnrich) {
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'activity_type' => 'queued',
                        'description' => 'Dispatched enrichment job pipeline.',
                    ]);

                    // Update status to Queued
                    $lead->update(['status' => 'Queued']);

                    EnrichLeadJob::withChain([
                        new DraftLeadEmailJob($lead),
                        new SyncLeadToSheetsJob($lead)
                    ])->dispatch($lead);
                }

                $addedCount++;
            }

            $processedLeads[] = $lead;
        }

        // Update import session tracking counts
        if ($session) {
            $session->increment('total_found', count($listings));
            $session->increment('imported', $addedCount);
            $session->increment('duplicates', $duplicateCount);
        }

        return [
            'added' => $addedCount,
            'duplicates' => $duplicateCount,
            'processed' => $processedLeads
        ];
    }
}
