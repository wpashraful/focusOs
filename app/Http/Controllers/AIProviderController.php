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
                'meta-llama/llama-3.3-70b-instruct:free',
                'meta-llama/llama-3.2-3b-instruct:free',
                'google/gemma-4-26b-a4b-it:free',
                'openrouter/free',
                'gemini-3.5-flash',
                'gemini-3.1-flash-lite',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
                'gemini-2.0-flash',
                'gemini-2.0-flash-lite',
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

        if ($providerKey === 'local') {
            try {
                $apiUrl = env('OLLAMA_API_URL', 'http://127.0.0.1:11434');
                $response = \Illuminate\Support\Facades\Http::timeout(3)
                    ->get("{$apiUrl}/api/tags");
                if ($response->successful()) {
                    $data = $response->json();
                    $localModels = [];
                    foreach ($data['models'] ?? [] as $m) {
                        $localModels[] = $m['name'];
                    }
                    if (!empty($localModels)) {
                        $models = $localModels;
                    }
                }
            } catch (\Exception $e) {
                // Fail silently and use fallbacks
            }
        } elseif ($providerKey === 'gemini' && env('GEMINI_API_KEY')) {
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
        } else {
            // Default: Fetch dynamic list from OpenRouter if configured
            if (config('services.openrouter.key') || env('OPENROUTER_API_KEY')) {
                try {
                    $apiKey = config('services.openrouter.key', env('OPENROUTER_API_KEY'));
                    $response = \Illuminate\Support\Facades\Http::timeout(5)
                        ->withHeaders([
                            'Authorization' => "Bearer {$apiKey}",
                        ])
                        ->get("https://openrouter.ai/api/v1/models");

                    if ($response->successful()) {
                        $data = $response->json();
                        $dynamicModels = [];
                        foreach ($data['data'] ?? [] as $m) {
                            $promptPrice = floatval($m['pricing']['prompt'] ?? 0);
                            $completionPrice = floatval($m['pricing']['completion'] ?? 0);
                            $isFree = ($promptPrice == 0.0 && $completionPrice == 0.0) || str_ends_with($m['id'], ':free');

                            if ($isFree) {
                                $dynamicModels[] = $m['id'];
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
        }

        return response()->json($models);
    }
}
