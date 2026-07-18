<?php

namespace App\Http\Controllers;

use App\Models\AIProvider;
use App\Models\ProjectAISetting;
use Illuminate\Http\Request;

class AIProviderController extends Controller
{
    public function index(Request $request)
    {
        $providers = AIProvider::where('is_active', true)->get();
        return response()->json($providers);
    }

    public function update(Request $request, $projectId)
    {
        $validated = $request->validate([
            'ai_provider_id' => 'required|exists:ai_providers,id',
            'model_name'     => 'required|string|max:100',
            'temperature'    => 'nullable|numeric|min:0|max:2',
            'max_tokens'     => 'nullable|integer|min:128|max:16384',
            'system_prompt'  => 'nullable|string|max:4000',
        ]);

        // Verify user owns the project
        $user    = $request->user();
        $project = $user->workspaces()->with('projects')
            ->get()->pluck('projects')->flatten()
            ->firstWhere('id', $projectId);

        if (!$project) {
            return response()->json(['error' => 'Project not found.'], 404);
        }

        ProjectAISetting::updateOrCreate(
            ['project_id' => $project->id],
            $validated
        );

        return response()->json(['success' => true]);
    }
}
