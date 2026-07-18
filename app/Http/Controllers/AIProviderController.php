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

    public function models(Request $request, AIProvider $provider)
    {
        $fallbackModels = [
            'gemini' => [
                'gemini-2.5-flash',
                'gemini-2.5-pro',
                'gemini-2.0-flash',
                'gemini-1.5-flash',
                'gemini-1.5-pro',
            ],
            'openai' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
            ],
            'local' => [
                'llama3',
                'mistral',
                'phi3',
                'gemma2',
            ]
        ];

        $providerKey = $provider->provider_key;
        $models = $fallbackModels[$providerKey] ?? [];

        // If Gemini and key is present, try to fetch dynamic list
        if ($providerKey === 'gemini' && env('GEMINI_API_KEY')) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->get("https://generativelanguage.googleapis.com/v1beta/models?key=" . env('GEMINI_API_KEY'));

                if ($response->successful()) {
                    $data = $response->json();
                    $dynamicModels = [];
                    foreach ($data['models'] ?? [] as $m) {
                        $methods = $m['supportedGenerationMethods'] ?? [];
                        if (in_array('generateContent', $methods)) {
                            // Strip "models/" prefix
                            $dynamicModels[] = str_replace('models/', '', $m['name']);
                        }
                    }
                    if (!empty($dynamicModels)) {
                        $models = array_values(array_unique($dynamicModels));
                    }
                }
            } catch (\Exception $e) {
                // Fail silently and use fallbacks
            }
        }

        return response()->json($models);
    }
}
