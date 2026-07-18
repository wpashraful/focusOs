<?php

namespace Database\Seeders;

use App\Models\AIProvider;
use Illuminate\Database\Seeder;

class AIProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name'               => 'Gemini (Google)',
                'provider_key'       => 'gemini',
                'supports_tools'     => true,
                'supports_streaming' => true,
                'supports_embeddings'=> true,
                'provider_dimension' => 768, // Gemini default embedding dimension
                'is_active'          => true,
            ],
            [
                'name'               => 'OpenAI',
                'provider_key'       => 'openai',
                'supports_tools'     => true,
                'supports_streaming' => true,
                'supports_embeddings'=> true,
                'provider_dimension' => 1536, // OpenAI default dimension
                'is_active'          => true,
            ],
            [
                'name'               => 'Ollama (Local)',
                'provider_key'       => 'local',
                'supports_tools'     => false,
                'supports_streaming' => true,
                'supports_embeddings'=> true,
                'provider_dimension' => 1024,
                'is_active'          => true,
            ]
        ];

        foreach ($providers as $p) {
            AIProvider::updateOrCreate(['provider_key' => $p['provider_key']], $p);
        }
    }
}
