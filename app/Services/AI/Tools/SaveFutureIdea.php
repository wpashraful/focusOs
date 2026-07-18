<?php

namespace App\Services\AI\Tools;

use App\Models\FutureIdea;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class SaveFutureIdea implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name'        => 'save_future_idea',
            'description' => 'Save a future business, feature, or task idea for backlog review.',
            'parameters'  => [
                'type'       => 'OBJECT',
                'properties' => [
                    'title' => [
                        'type'        => 'STRING',
                        'description' => 'A short summary/title of the idea.'
                    ],
                    'content' => [
                        'type'        => 'STRING',
                        'description' => 'Extended details or notes regarding the idea.'
                    ]
                ],
                'required' => ['title']
            ]
        ];
    }

    public function execute(array $args, ?Project $project): array
    {
        $idea = FutureIdea::create([
            'user_id' => Auth::id(),
            'title'   => $args['title'],
            'content' => $args['content'] ?? null,
        ]);

        // Fire event (Step 6.5)
        event(new \App\Events\FutureIdeaSaved($idea));

        return [
            'result' => "Success: Idea \"{$idea->title}\" (ID: {$idea->id}) has been saved to your backlog."
        ];
    }
}
