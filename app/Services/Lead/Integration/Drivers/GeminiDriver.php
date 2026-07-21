<?php

namespace App\Services\Lead\Integration\Drivers;

use App\Services\Lead\Integration\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiDriver implements AIProviderInterface
{
    public function audit(array $leadData, array $config): array
    {
        $apiKey = $config['api_key'] ?? null;
        if (!$apiKey) {
            throw new Exception("Gemini API key not configured.");
        }

        $model = $config['model'] ?? 'gemini-1.5-flash';
        $systemInstruction = 'You are an expert lead enrichment AI. Analyze the business details, website phone/emails, technology, and social media presence to generate a marketing audit in JSON format. The JSON must contain exactly these keys: "strengths" (array of strings), "gaps" (array of strings showing website or marketing weaknesses), "suggestions" (array of strings showing action items), and "cold_email_pitch" (a short, highly-personalized sentence referencing their business that can be used as the intro of a cold email).';

        $promptText = "Company: " . ($leadData['name'] ?? 'N/A') . "\n" .
                      "Address: " . ($leadData['address'] ?? 'N/A') . "\n" .
                      "Rating: " . ($leadData['rating'] ?? 'N/A') . " (" . ($leadData['reviews_count'] ?? 0) . " reviews)\n" .
                      "Website: " . ($leadData['website'] ?? 'N/A') . "\n" .
                      "Emails: " . implode(', ', $leadData['emails'] ?? []) . "\n" .
                      "Social Links: " . json_encode($leadData['socials'] ?? []) . "\n" .
                      "Technology: " . ($leadData['website_tech'] ?? 'Custom HTML/JS') . "\n" .
                      "Scraped Content Sample:\n" . substr($leadData['background'] ?? '', 0, 1000);

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(15)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $promptText]
                        ]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemInstruction]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.4
                ]
            ]);

            if ($response->successful()) {
                $candidates = $response->json('candidates.0.content.parts.0.text');
                if ($candidates) {
                    $content = json_decode($candidates, true);
                    if (is_array($content)) {
                        return [
                            'strengths' => $content['strengths'] ?? [],
                            'gaps' => $content['gaps'] ?? [],
                            'suggestions' => $content['suggestions'] ?? [],
                            'cold_email_pitch' => $content['cold_email_pitch'] ?? '',
                        ];
                    }
                }
            }

            Log::error("Gemini API response error: " . $response->body());
        } catch (Exception $e) {
            Log::error("Gemini API exception: " . $e->getMessage());
        }

        return [];
    }
}
