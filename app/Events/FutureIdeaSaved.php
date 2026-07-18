<?php

namespace App\Events;

use App\Models\FutureIdea;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FutureIdeaSaved
{
    use Dispatchable, SerializesModels;

    public FutureIdea $idea;

    public function __construct(FutureIdea $idea)
    {
        $this->idea = $idea;
    }
}
