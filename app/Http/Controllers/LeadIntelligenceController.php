<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\LeadActivity;
use App\Models\IntegrationSetting;
use App\Models\ImportSession;
use App\Models\RootKeyword;
use App\Models\SearchVariation;
use App\Models\SearchLocation;
use App\Models\SearchCoverage;
use App\Models\Industry;
use App\Services\AI\AIProviderInterface;
use App\Services\Lead\LeadImportService;
use App\Services\Lead\LeadEnrichmentService;
use App\Services\Lead\LeadEmailService;
use App\Services\Lead\LeadExportService;
use App\Jobs\Lead\EnrichLeadJob;
use App\Jobs\Lead\DraftLeadEmailJob;
use App\Jobs\Lead\SyncLeadToSheetsJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LeadIntelligenceController extends Controller
{
    /**
     * Get the active workspace for the authenticated user.
     */
    protected function getActiveWorkspace(Request $request)
    {
        $project = $request->user()
            ->workspaces()->with('projects')
            ->get()->pluck('projects')->flatten()
            ->where('status', 'active')->first();

        if ($project) {
            return $request->user()->workspaces()->find($project->workspace_id);
        }

        return $request->user()->workspaces()->first();
    }

    /**
     * Dashboard View & Filters
     */
    public function index(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (!$workspace) {
            return redirect()->route('workspaces.index')->with('error', 'Please create a workspace first.');
        }

        // 1. Calculate Stats
        $stats = [
            'total' => Lead::where('workspace_id', $workspace->id)->count(),
            'scanned' => Lead::where('workspace_id', $workspace->id)
                ->whereNotIn('status', ['Imported', 'Queued', 'Lost'])
                ->count(),
            'emails_found' => Lead::where('workspace_id', $workspace->id)
                ->whereNotNull('email')
                ->where('email', '!=', 'N/A')
                ->count(),
            'avg_score' => round(
                Lead::where('workspace_id', $workspace->id)
                    ->whereNotIn('status', ['Imported', 'Queued', 'Lost'])
                    ->avg('lead_score') ?? 0
            ),
            'pending_emails' => Lead::where('workspace_id', $workspace->id)
                ->where('status', 'Ready')
                ->count(),
        ];

        // 2. Fetch leads with filters
        $query = Lead::where('workspace_id', $workspace->id)
            ->with(['socials', 'audits', 'emails']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('website', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('score')) {
            if ($request->score === 'high') {
                $query->where('lead_score', '>=', 75);
            } elseif ($request->score === 'medium') {
                $query->where('lead_score', '>=', 50)->where('lead_score', '<', 75);
            } else {
                $query->where('lead_score', '<', 50);
            }
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // 3. Fetch Workspace Integrations/Settings
        $settingsQuery = IntegrationSetting::where('workspace_id', $workspace->id)->get()->pluck('value', 'key');
        $settings = [
            'ai_provider' => $settingsQuery->get('ai_provider', 'openai'),
            'ai_api_key' => $settingsQuery->get('ai_api_key') ? '••••••••' : '',
            'ai_model' => $settingsQuery->get('ai_model', ''),
            'crawl_timeout' => (int) $settingsQuery->get('crawl_timeout', 15),
            'auto_enrich' => filter_var($settingsQuery->get('auto_enrich', true), FILTER_VALIDATE_BOOLEAN),
            'google_sheets_spreadsheet_id' => $settingsQuery->get('google_sheets_spreadsheet_id', ''),
            'google_sheets_sheet_name' => $settingsQuery->get('google_sheets_sheet_name', 'Sheet1'),
            'smtp_host' => $settingsQuery->get('smtp_host', ''),
            'smtp_port' => (int) $settingsQuery->get('smtp_port', 587),
            'smtp_username' => $settingsQuery->get('smtp_username', ''),
            'smtp_password' => $settingsQuery->get('smtp_password') ? '••••••••' : '',
            'smtp_encryption' => $settingsQuery->get('smtp_encryption', 'tls'),
            'smtp_from_address' => $settingsQuery->get('smtp_from_address', ''),
            'smtp_from_name' => $settingsQuery->get('smtp_from_name', ''),
        ];

        $projects = $workspace->projects()->get();

        $industries = Industry::orderBy('name', 'asc')->get();

        $keywords = RootKeyword::with('variations')
            ->orderBy('keyword', 'asc')
            ->get();

        $locations = SearchLocation::where('is_active', true)
            ->orderBy('state', 'asc')
            ->orderBy('city', 'asc')
            ->get();

        $coverages = SearchCoverage::where('workspace_id', $workspace->id)->get();

        $sessions = ImportSession::where('workspace_id', $workspace->id)
            ->with(['coverage.variation', 'coverage.location'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return Inertia::render('Leads/Index', [
            'stats' => $stats,
            'leads' => $leads,
            'settings' => $settings,
            'workspace' => $workspace,
            'projects' => $projects,
            'industries' => $industries,
            'keywords' => $keywords,
            'locations' => $locations,
            'coverages' => $coverages,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Manually Trigger Scan Pipeline
     */
    public function scan(Lead $lead)
    {
        $lead->update(['status' => 'Queued']);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'activity_type' => 'queued',
            'description' => 'Manually queued scan job pipeline.'
        ]);

        EnrichLeadJob::withChain([
            new DraftLeadEmailJob($lead),
            new SyncLeadToSheetsJob($lead)
        ])->dispatch($lead);

        return back()->with('success', "Lead Intelligence pipeline queued for {$lead->name}.");
    }

    /**
     * Send Cold Outreach Email
     */
    public function sendEmail(Lead $lead, LeadEmailService $emailService)
    {
        $email = $lead->emails()->first();

        if (!$email) {
            try {
                $email = $emailService->draftEmail($lead);
            } catch (\Exception $e) {
                return back()->with('error', "Cannot send email: " . $e->getMessage());
            }
        }

        $sent = $emailService->sendEmail($email);

        if ($sent) {
            return back()->with('success', "Outreach email sent successfully to {$lead->email}.");
        }

        return back()->with('error', "Failed sending outreach email. Check Integrations log.");
    }

    /**
     * Bulk Enrich Queue
     */
    public function enrichAll(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (!$workspace) {
            return back()->with('error', 'Workspace not found.');
        }

        $leadsToEnrich = Lead::where('workspace_id', $workspace->id)
            ->whereIn('status', ['Imported', 'Failed'])
            ->get();

        $count = 0;
        foreach ($leadsToEnrich as $lead) {
            $lead->update(['status' => 'Queued']);
            
            LeadActivity::create([
                'lead_id' => $lead->id,
                'activity_type' => 'queued',
                'description' => 'Queued by bulk enrich trigger.'
            ]);

            EnrichLeadJob::withChain([
                new DraftLeadEmailJob($lead),
                new SyncLeadToSheetsJob($lead)
            ])->dispatch($lead);
            
            $count++;
        }

        return back()->with('success', "Enrichment pipeline queued for {$count} leads.");
    }

    /**
     * Save Integration Credentials
     */
    public function saveSettings(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (!$workspace) {
            return back()->with('error', 'Workspace not found.');
        }

        $validated = $request->validate([
            'ai_provider' => 'nullable|string|in:openai,gemini',
            'ai_api_key' => 'nullable|string|max:1000',
            'ai_model' => 'nullable|string|max:255',
            'crawl_timeout' => 'nullable|integer|min:5|max:120',
            'auto_enrich' => 'nullable|boolean',
            'google_sheets_spreadsheet_id' => 'nullable|string|max:255',
            'google_sheets_sheet_name' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:1000',
            'smtp_encryption' => 'nullable|string|in:ssl,tls,none',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name' => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            // Do not overwrite sensitive keys if value is masked '••••••••'
            if (in_array($key, ['ai_api_key', 'smtp_password']) && $value === '••••••••') {
                continue;
            }

            IntegrationSetting::updateOrCreate(
                ['workspace_id' => $workspace->id, 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Integration settings updated successfully.');
    }

    /**
     * Export Leads to CSV
     */
    public function exportCsv(Request $request, LeadExportService $exportService)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (!$workspace) {
            return back()->with('error', 'Workspace not found.');
        }

        $leads = Lead::where('workspace_id', $workspace->id)
            ->whereNotIn('status', ['Lost'])
            ->with([
                'session.coverage.variation.rootKeyword',
                'session.coverage.location'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $csvContent = $exportService->exportToCsv($leads);

        $filename = 'leads_export_' . date('Ymd_His') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * API Endpoint to ingest leads from Chrome Extension
     */
    public function storeApi(Request $request, LeadImportService $importService)
    {
        // 1. Identify Workspace (via parameter, default to 1)
        $workspaceId = (int) $request->input('workspace_id', 1);
        $projectId = $request->input('project_id') ? (int) $request->project_id : null;
        $importSessionId = $request->input('import_session_id') ? (int) $request->import_session_id : null;

        $data = $request->json()->all();

        // Handle empty bodies
        if (empty($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Empty payload received.'
            ], 400)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // Handle single object vs list
        $listings = is_array($data) && isset($data[0]) ? $data : [$data];

        try {
            $result = $importService->importCollection($listings, $workspaceId, $projectId, $importSessionId);

            return response()->json([
                'status' => 'success',
                'message' => "Leads synchronized. Added: {$result['added']}, Duplicates: {$result['duplicates']}.",
                'added' => $result['added'],
                'duplicates' => $result['duplicates']
            ], 201)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (\Exception $e) {
            Log::error("API Leads Import failed: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }
    }

    /**
     * API Endpoint to poll the next pending scrape task
     */
    public function pollTasks(Request $request)
    {
        $clientId = $request->input('client_id');
        $workspaceId = (int) $request->input('workspace_id', 1);

        $coverage = SearchCoverage::where('workspace_id', $workspaceId)
            ->where('searched', false)
            ->whereIn('status', ['unchecked', 'pending'])
            ->with(['variation.rootKeyword', 'location'])
            ->first();

        if (!$coverage) {
            return response()->json(['task' => null], 200)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $coverage->update([
            'status' => 'running',
            'last_scraped' => now(),
        ]);

        return response()->json([
            'task' => [
                'task_id' => $coverage->id,
                'search_query' => $coverage->variation->keyword . ' ' . $coverage->location->city,
                'country' => $coverage->location->country,
                'state' => $coverage->location->state,
                'city' => $coverage->location->city,
                'language' => 'en',
                'max_results' => 120,
                'radius' => null,
                'sort' => null,
                'filters' => null,
            ]
        ], 200)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * API Endpoint to start an import session
     */
    public function startSession(Request $request)
    {
        $workspaceId = (int) $request->input('workspace_id', 1);
        $projectId = $request->input('project_id') ? (int) $request->project_id : null;
        $coverageId = $request->input('scrape_task_id') ?: $request->input('search_coverage_id');

        $session = ImportSession::create([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'search_coverage_id' => $coverageId ? (int) $coverageId : null,
            'status' => 'running',
            'started_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'import_session_id' => $session->id
        ], 201)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * API Endpoint to complete an import session and its search coverage task
     */
    public function finishSession(Request $request)
    {
        $sessionId = (int) $request->input('import_session_id');
        $session = ImportSession::find($sessionId);

        if (!$session) {
            return response()->json(['status' => 'error', 'message' => 'Session not found'], 404)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $session->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        if ($session->search_coverage_id) {
            $coverage = SearchCoverage::find($session->search_coverage_id);
            if ($coverage) {
                $coverage->update([
                    'searched' => true,
                    'status' => 'completed',
                    'lead_count' => $session->total_found,
                    'last_scraped' => now(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Session and coverage completed successfully.'
        ], 200)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Run Coverage Matrix for selected variations and cities
     */
    public function runCoverageMatrix(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        $request->validate([
            'variations' => 'required|array',
            'variations.*' => 'required|string',
            'cities' => 'required|array',
            'cities.*' => 'required|exists:search_locations,id',
        ]);

        // Find or create variations by their names or resolve their IDs
        $variationIds = SearchVariation::whereIn('keyword', $request->variations)->pluck('id')->toArray();
        $cityIds = $request->cities;

        $created = 0;
        foreach ($variationIds as $varId) {
            foreach ($cityIds as $cityId) {
                $coverage = SearchCoverage::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'variation_id' => $varId,
                        'city_id' => $cityId,
                    ]
                );

                if (!$coverage->searched) {
                    $coverage->update(['status' => 'pending']);
                    $created++;
                }
            }
        }

        return back()->with('success', "Enqueued {$created} new matrix search tasks.");
    }

    /**
     * AI Suggest Keyword Variations from root keyword
     */
    public function suggestKeywords(Request $request, AIProviderInterface $aiProvider)
    {
        $workspace = $this->getActiveWorkspace($request);
        $request->validate([
            'root_keyword' => 'required|string|max:100',
            'industry_id' => 'nullable|exists:industries,id'
        ]);

        $rootWord = trim($request->root_keyword);
        
        $project = $workspace->projects()->where('status', 'active')->first() 
                ?: $workspace->projects()->first();

        $prompt = "Generate exactly 20 search query variations for local business maps search based on the root industry word: '{$rootWord}'. " .
                  "For example, if the root is 'Dentist', generate variations like: 'Dental Clinic', 'Family Dentist', 'Emergency Dentist', 'Cosmetic Dentist', 'Dental Implants'. " .
                  "Format your response as a valid, flat JSON array of strings: [\"Variation 1\", \"Variation 2\", ...]. Return only the JSON block without markdown formatting or other explanations.";

        try {
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];

            $res = $aiProvider->chat($messages, [
                'temperature' => 0.4,
                'project' => $project
            ]);

            $responseJson = trim($res['text'] ?? '');
            
            if (str_starts_with($responseJson, '```json')) {
                $responseJson = substr($responseJson, 7);
            } elseif (str_starts_with($responseJson, '```')) {
                $responseJson = substr($responseJson, 3);
            }
            if (str_ends_with($responseJson, '```')) {
                $responseJson = substr($responseJson, 0, -3);
            }
            $responseJson = trim($responseJson);

            $parsed = json_decode($responseJson, true);
            if (is_array($parsed) && isset($parsed['variations'])) {
                $variations = $parsed['variations'];
            } elseif (is_array($parsed) && !isset($parsed[0])) {
                $variations = array_values($parsed);
            } else {
                $variations = $parsed;
            }

            if (!is_array($variations) || empty($variations)) {
                throw new \Exception("AI response could not be parsed as a flat array of strings: " . $responseJson);
            }

            $rootKeyword = RootKeyword::firstOrCreate(
                ['keyword' => $rootWord],
                [
                    'industry_id' => $request->industry_id,
                    'slug' => Str::slug($rootWord),
                    'is_active' => true,
                ]
            );

            foreach ($variations as $var) {
                SearchVariation::updateOrCreate([
                    'root_keyword_id' => $rootKeyword->id,
                    'keyword' => trim($var)
                ], [
                    'source' => 'AI',
                    'is_active' => true
                ]);
            }

            return back()->with('success', 'Successfully generated 20 keyword variations.');
        } catch (\Exception $e) {
            Log::error("AI Keyword Suggestion failed: " . $e->getMessage());
            return back()->with('error', 'AI suggestion failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete root keyword (category)
     */
    public function destroyKeyword(RootKeyword $keyword)
    {
        $keyword->delete();
        return back()->with('success', 'Category deleted successfully.');
    }

    /**
     * Store new variation manually under a root keyword
     */
    public function storeVariation(Request $request)
    {
        $request->validate([
            'root_keyword_id' => 'required|exists:root_keywords,id',
            'keyword' => 'required|string|max:255'
        ]);

        SearchVariation::create([
            'root_keyword_id' => $request->root_keyword_id,
            'keyword' => trim($request->keyword),
            'source' => 'Manual',
            'is_active' => true
        ]);

        return back()->with('success', 'Search variation added successfully.');
    }

    /**
     * Delete search variation
     */
    public function destroyVariation(SearchVariation $variation)
    {
        $variation->delete();
        return back()->with('success', 'Search variation deleted successfully.');
    }

    /**
     * Import Keywords from CSV
     */
    public function importKeywordsCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return back()->with('error', 'Cannot open uploaded CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header || count($header) < 2) {
            fclose($handle);
            return back()->with('error', 'Invalid CSV format. Expected: Industry,Keyword');
        }

        $industryIdx = -1;
        $keywordIdx = -1;

        foreach ($header as $index => $col) {
            $colName = strtolower(trim($col));
            if ($colName === 'industry') {
                $industryIdx = $index;
            } elseif ($colName === 'keyword') {
                $keywordIdx = $index;
            }
        }

        if ($industryIdx === -1 || $keywordIdx === -1) {
            $industryIdx = 0;
            $keywordIdx = 1;
        }

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || !isset($row[$industryIdx]) || !isset($row[$keywordIdx])) {
                continue;
            }

            $industryName = trim($row[$industryIdx]);
            $keywordVal = trim($row[$keywordIdx]);

            if (empty($industryName) || empty($keywordVal)) {
                continue;
            }

            $industry = Industry::firstOrCreate(['name' => $industryName]);

            RootKeyword::firstOrCreate(
                ['keyword' => $keywordVal],
                [
                    'industry_id' => $industry->id,
                    'slug' => Str::slug($keywordVal),
                    'is_system' => false,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Successfully imported {$imported} keywords.");
    }

    /**
     * Import Locations/Cities from CSV
     */
    public function importCitiesCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB limit
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return back()->with('error', 'Cannot open uploaded CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header || count($header) < 3) {
            fclose($handle);
            return back()->with('error', 'Invalid CSV format. Expected at least: Country,State,City');
        }

        $countryIdx = -1;
        $stateIdx = -1;
        $cityIdx = -1;
        $latIdx = -1;
        $lonIdx = -1;
        $popIdx = -1;

        foreach ($header as $index => $col) {
            $colName = strtolower(trim($col));
            if (str_contains($colName, 'country')) {
                $countryIdx = $index;
            } elseif (str_contains($colName, 'state')) {
                $stateIdx = $index;
            } elseif (str_contains($colName, 'city')) {
                $cityIdx = $index;
            } elseif (str_contains($colName, 'lat')) {
                $latIdx = $index;
            } elseif (str_contains($colName, 'lon') || str_contains($colName, 'lng')) {
                $lonIdx = $index;
            } elseif (str_contains($colName, 'pop')) {
                $popIdx = $index;
            }
        }

        if ($countryIdx === -1) $countryIdx = 0;
        if ($stateIdx === -1) $stateIdx = 1;
        if ($cityIdx === -1) $cityIdx = 2;

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || !isset($row[$cityIdx]) || !isset($row[$stateIdx])) {
                continue;
            }

            $country = ($countryIdx !== -1 && isset($row[$countryIdx])) ? trim($row[$countryIdx]) : 'US';
            $state = trim($row[$stateIdx]);
            $city = trim($row[$cityIdx]);

            if (empty($city) || empty($state)) {
                continue;
            }

            $latitude = ($latIdx !== -1 && isset($row[$latIdx])) ? (double) $row[$latIdx] : null;
            $longitude = ($lonIdx !== -1 && isset($row[$lonIdx])) ? (double) $row[$lonIdx] : null;
            $population = ($popIdx !== -1 && isset($row[$popIdx])) ? (int) $row[$popIdx] : null;

            SearchLocation::updateOrCreate(
                [
                    'country' => $country ?: 'US',
                    'state' => $state,
                    'city' => $city,
                ],
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'population' => $population,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Successfully imported {$imported} locations.");
    }

    /**
     * Export Master Keywords library to CSV
     */
    public function exportKeywordsCsv()
    {
        $keywords = RootKeyword::with(['industry', 'variations'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="keywords_export_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($keywords) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Industry', 'Keyword', 'Variation', 'Source']);

            foreach ($keywords as $kw) {
                $industry = $kw->industry ? $kw->industry->name : 'Uncategorized';
                
                if ($kw->variations->isEmpty()) {
                    fputcsv($file, [$industry, $kw->keyword, '', 'Manual']);
                } else {
                    foreach ($kw->variations as $var) {
                        fputcsv($file, [$industry, $kw->keyword, $var->keyword, $var->source]);
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Target Locations/Cities to CSV
     */
    public function exportCitiesCsv()
    {
        $locations = SearchLocation::where('is_active', true)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cities_export_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($locations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Country', 'State', 'City', 'Latitude', 'Longitude', 'Population']);

            foreach ($locations as $loc) {
                fputcsv($file, [
                    $loc->country,
                    $loc->state,
                    $loc->city,
                    $loc->latitude,
                    $loc->longitude,
                    $loc->population
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Store new Industry Category
     */
    public function storeIndustry(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:industries,name'
        ]);

        Industry::create([
            'name' => trim($request->name),
            'priority' => 0
        ]);

        return back()->with('success', 'Industry category created successfully.');
    }

    /**
     * Delete Industry Category
     */
    public function destroyIndustry(Industry $industry)
    {
        $industry->delete();
        return back()->with('success', 'Industry category deleted successfully.');
    }

    /**
     * Store new Search Location (City)
     */
    public function storeLocation(Request $request)
    {
        $request->validate([
            'state' => 'required|string|max:50',
            'city' => 'required|string|max:100',
            'country' => 'nullable|string|max:10',
            'population' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        SearchLocation::firstOrCreate(
            [
                'country' => $request->country ?: 'US',
                'state' => trim($request->state),
                'city' => trim($request->city),
            ],
            [
                'population' => $request->population,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_active' => true
            ]
        );

        return back()->with('success', 'Location created successfully.');
    }

    /**
     * Delete Search Location
     */
    public function destroyLocation(SearchLocation $location)
    {
        $location->delete();
        return back()->with('success', 'Location deleted successfully.');
    }

    /**
     * Run targeting campaign for selected target mode and target ID.
     */
    public function runTargetingCampaign(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        if (!$workspace) {
            return back()->with('error', 'Workspace not found.');
        }

        $request->validate([
            'target_type' => 'required|string|in:industry,root_keyword,city',
            'target_id' => 'required|integer',
        ]);

        $targetType = $request->target_type;
        $targetId = $request->target_id;

        $variationIds = [];
        $cityIds = [];

        if ($targetType === 'industry') {
            // Find all variations under this industry
            $variationIds = SearchVariation::whereHas('rootKeyword', function($query) use ($targetId) {
                $query->where('industry_id', $targetId);
            })->pluck('id')->toArray();

            // Find all active locations
            $cityIds = SearchLocation::where('is_active', true)->pluck('id')->toArray();

        } elseif ($targetType === 'root_keyword') {
            // Find all variations under this root keyword
            $variationIds = SearchVariation::where('root_keyword_id', $targetId)->pluck('id')->toArray();

            // Find all active locations
            $cityIds = SearchLocation::where('is_active', true)->pluck('id')->toArray();

        } elseif ($targetType === 'city') {
            // Find all active variations
            $variationIds = SearchVariation::where('is_active', true)->pluck('id')->toArray();

            // Check if this city is active and get its ID
            $cityIds = SearchLocation::where('id', $targetId)->where('is_active', true)->pluck('id')->toArray();
        }

        if (empty($variationIds) || empty($cityIds)) {
            return back()->with('error', 'No search variations or active locations found for this target.');
        }

        $created = 0;
        foreach ($variationIds as $varId) {
            foreach ($cityIds as $cityId) {
                $coverage = SearchCoverage::firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'variation_id' => $varId,
                        'city_id' => $cityId,
                    ]
                );

                // Duplicate Check: Only set status to pending if searched is false and not already running, pending, or completed
                if (!$coverage->searched && !in_array($coverage->status, ['pending', 'running', 'completed'])) {
                    $coverage->update(['status' => 'pending']);
                    $created++;
                }
            }
        }

        return back()->with('success', "Targeting campaign enqueued {$created} new search tasks (skipped completed/running tasks).");
    }
}
