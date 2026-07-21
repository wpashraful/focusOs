<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\LeadActivity;
use App\Models\IntegrationSetting;
use Exception;

class LeadEmailService
{
    /**
     * Draft outreach proposal based on AI audit.
     */
    public function draftEmail(Lead $lead): LeadEmail
    {
        $audit = $lead->audits()->first();
        if (!$audit) {
            throw new Exception("Cannot draft email: lead has not been audited.");
        }

        $gapsList = "";
        foreach (($audit->gaps ?? []) as $gap) {
            $gapsList .= "- {$gap}\n";
        }

        $suggestionsList = "";
        foreach (($audit->suggestions ?? []) as $suggestion) {
            $suggestionsList .= "- {$suggestion}\n";
        }

        $pitch = $audit->cold_email_pitch ?? "I noticed your business on Google Maps and wanted to share a few ways you can attract more clients.";

        $body = "Hi team at {$lead->name},\n\n" .
               "{$pitch}\n\n" .
               "After performing a quick audit of your online channels, we identified a few key opportunities:\n" .
               "{$gapsList}\n" .
               "Here is what we recommend doing next to capture more clients:\n" .
               "{$suggestionsList}\n" .
               "Are you available for a quick 10-minute call this Thursday to discuss how we can implement these changes for {$lead->name}?\n\n" .
               "Best regards,\n" .
               "Lead Intelligence Outreach Team";

        $subject = "Leverage more customers for {$lead->name}";

        $email = LeadEmail::updateOrCreate([
            'lead_id' => $lead->id
        ], [
            'subject' => $subject,
            'body' => $body,
            'status' => 'draft'
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'activity_type' => 'email_drafted',
            'description' => 'Cold outreach email proposal drafted.'
        ]);

        return $email;
    }

    /**
     * Dispatch live outreach email.
     */
    public function sendEmail(LeadEmail $email): bool
    {
        $lead = $email->lead;
        if (!$lead) {
            return false;
        }

        // Fetch SMTP integration configurations
        $hostSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_host')->first();
        $portSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_port')->first();
        $usernameSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_username')->first();
        $passwordSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_password')->first();
        $encryptionSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_encryption')->first();
        $fromAddressSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_from_address')->first();
        $fromNameSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)->where('key', 'smtp_from_name')->first();

        $config = [
            'host' => $hostSetting ? $hostSetting->value : null,
            'port' => $portSetting ? (int) $portSetting->value : 587,
            'username' => $usernameSetting ? $usernameSetting->value : null,
            'password' => $passwordSetting ? $passwordSetting->value : null,
            'encryption' => $encryptionSetting ? $encryptionSetting->value : 'tls',
            'from_address' => $fromAddressSetting ? $fromAddressSetting->value : null,
            'from_name' => $fromNameSetting ? $fromNameSetting->value : null,
        ];

        // Resolve active outreach driver (SMTP driver)
        $outreachDriver = app(\App\Services\Lead\Integration\Drivers\SMTPDriver::class);

        $emailData = [
            'to' => $lead->email,
            'subject' => $email->subject,
            'body' => $email->body
        ];

        $success = $outreachDriver->send($emailData, $config);

        if ($success) {
            $email->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            $lead->update([
                'status' => 'Contacted'
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'activity_type' => 'email_sent',
                'description' => "Outreach email sent successfully to {$lead->email}."
            ]);
        } else {
            $email->update([
                'status' => 'failed'
            ]);
        }

        return $success;
    }
}
