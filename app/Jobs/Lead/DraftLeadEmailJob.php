<?php

namespace App\Jobs\Lead;

use App\Models\Lead;
use App\Services\Lead\LeadEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DraftLeadEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $lead;

    /**
     * Create a new job instance.
     */
    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * Execute the job.
     */
    public function handle(LeadEmailService $emailService): void
    {
        // Skip if the lead status is already Won, Lost, etc.
        if (in_array($this->lead->status, ['Won', 'Lost'])) {
            return;
        }

        $emailService->draftEmail($this->lead);

        // Transition status to Ready
        $this->lead->update(['status' => 'Ready']);
    }
}
