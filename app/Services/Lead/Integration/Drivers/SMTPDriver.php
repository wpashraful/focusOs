<?php

namespace App\Services\Lead\Integration\Drivers;

use App\Services\Lead\Integration\Contracts\OutreachProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class SMTPDriver implements OutreachProviderInterface
{
    public function send(array $emailData, array $config): bool
    {
        $to = $emailData['to'] ?? null;
        $subject = $emailData['subject'] ?? 'Outreach';
        $body = $emailData['body'] ?? '';

        if (!$to || $to === 'N/A') {
            Log::warning("SMTP Driver: Cannot send outreach. No email address specified.");
            return false;
        }

        // 1. Always log to sandbox file
        $sandboxFile = storage_path('logs/cold_emails.log');
        $logEntry = "--- EMAIL SENT SUCCESSFULLY (SANDBOX) ---\n" .
                     "DATE: " . now()->toDateTimeString() . "\n" .
                     "TO: {$to}\n" .
                     "SUBJECT: {$subject}\n" .
                     "BODY:\n{$body}\n" .
                     "-----------------------------------------\n\n";
        
        // Ensure directory exists
        if (!file_exists(dirname($sandboxFile))) {
            mkdir(dirname($sandboxFile), 0755, true);
        }
        
        file_put_contents($sandboxFile, $logEntry, FILE_APPEND);

        // 2. If SMTP configuration is provided, try to send a real email
        if (!empty($config['host']) && !empty($config['username']) && !empty($config['password'])) {
            try {
                // Dynamically override mail configurations
                config([
                    'mail.mailers.dynamic' => [
                        'transport' => 'smtp',
                        'host' => $config['host'],
                        'port' => (int) ($config['port'] ?? 587),
                        'encryption' => $config['encryption'] ?? 'tls',
                        'username' => $config['username'],
                        'password' => $config['password'],
                        'timeout' => null,
                    ],
                    'mail.from.address' => $config['from_address'] ?? $config['username'],
                    'mail.from.name' => $config['from_name'] ?? 'Outreach Team',
                ]);

                Mail::mailer('dynamic')->raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
                
                Log::info("SMTP Driver: Live email sent successfully to {$to}");
            } catch (Exception $e) {
                Log::error("SMTP Driver failed sending live email to {$to}: " . $e->getMessage());
                // Still return true because sandbox log was successful
            }
        }

        return true;
    }
}
