<?php

namespace App\Services\AI\Tools;

use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class QueryLeads implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'query_leads',
            'description' => 'Search and query leads inside the user\'s active workspace. You can search by business name, website, phone, location/address, rating, lead score, or pipeline status.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'search' => [
                        'type'        => 'STRING',
                        'description' => 'A search query matching business name, website domain, phone number, email, or address.'
                    ],
                    'status' => [
                        'type'        => 'STRING',
                        'description' => 'Filter by pipeline status: Imported, Queued, Website Scanned, Email Found, AI Audited, Ready, Contacted, Interested, Won, Lost'
                    ],
                    'min_score' => [
                        'type'        => 'INTEGER',
                        'description' => 'Filter by minimum lead score (0-100).'
                    ],
                    'has_email' => [
                        'type'        => 'BOOLEAN',
                        'description' => 'Filter leads that have a valid email address found (not N/A).'
                    ],
                    'missing_social' => [
                        'type'        => 'STRING',
                        'description' => 'Filter leads missing a specific platform link: facebook, instagram, linkedin, youtube, whatsapp'
                    ],
                ]
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['result' => "Error: User is not authenticated."];
        }

        // Identify workspace from active project context, or fallback to first workspace
        $workspaceId = null;
        if ($project) {
            $workspaceId = $project->workspace_id;
        } else {
            $workspace = $user->workspaces()->first();
            if ($workspace) {
                $workspaceId = $workspace->id;
            }
        }

        if (!$workspaceId) {
            return ['result' => "Error: No active workspace found. Please create a workspace first."];
        }

        $query = Lead::where('workspace_id', $workspaceId);

        // Filters
        if (!empty($args['search'])) {
            $search = $args['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('website', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        if (isset($args['min_score'])) {
            $query->where('lead_score', '>=', (int) $args['min_score']);
        }

        if (!empty($args['has_email'])) {
            $query->whereNotNull('email')->where('email', '!=', 'N/A');
        }

        if (!empty($args['missing_social'])) {
            $platform = strtolower(trim($args['missing_social']));
            $query->whereDoesntHave('socials', function($q) use ($platform) {
                $q->where('platform', $platform);
            });
        }

        // Limit results to avoid token limit issues
        $leads = $query->orderBy('lead_score', 'desc')->limit(10)->get();

        if ($leads->isEmpty()) {
            return ['result' => "No leads found matching your criteria in the active workspace."];
        }

        // Build Markdown response table
        $markdown = "Found " . $leads->count() . " leads in the active workspace matching your criteria:\n\n";
        $markdown .= "| Score | Company Name | Email | Phone | Status |\n";
        $markdown .= "|---|---|---|---|---|\n";

        foreach ($leads as $lead) {
            $email = ($lead->email && $lead->email !== 'N/A') ? $lead->email : '*Not Found*';
            $phone = $lead->phone ? '+' . $lead->phone : '*Not Found*';
            $markdown .= "| **{$lead->lead_score}** | {$lead->name} | {$email} | {$phone} | `{$lead->status}` |\n";
        }

        if ($query->count() > 10) {
            $markdown .= "\n*(Showing top 10 results. Total matches: " . $query->count() . ")*";
        }

        return ['result' => $markdown];
    }
}
