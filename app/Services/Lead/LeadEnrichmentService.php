<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Models\LeadSocial;
use App\Models\LeadAudit;
use App\Models\LeadActivity;
use App\Models\IntegrationSetting;
use App\Services\Lead\Integration\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Log;
use Exception;

class LeadEnrichmentService
{
    /**
     * Enrich a lead: scrap website, detect tech, call AI auditor, calculate score.
     */
    public function enrich(Lead $lead): void
    {
        $lead->update(['status' => 'Website Scanned']);

        try {
            $htmlContent = '';
            $homepageData = [];
            $contactPagesData = [];
            $techDetected = null;

            // Fetch configurable crawl timeout
            $timeoutSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)
                ->where('key', 'crawl_timeout')
                ->first();
            $timeout = $timeoutSetting ? (int) $timeoutSetting->value : 15;

            // 1. Website Scan
            if ($lead->website && filter_var($lead->website, FILTER_VALIDATE_URL)) {
                $htmlContent = $this->fetchUrlWithCurl($lead->website, $timeout);

                if ($htmlContent) {
                    // Detect Technology Signature
                    $techDetected = $this->detectTechnology($htmlContent);

                    // Extract details from Homepage
                    $homepageData = $this->extractDetailsFromHtml($htmlContent, $lead->website);

                    // Find subpage links
                    $subpageUrls = $this->findContactSubpages($htmlContent, $lead->website);

                    // Scan found contact pages
                    foreach ($subpageUrls as $subpageUrl) {
                        try {
                            $subpageHtml = $this->fetchUrlWithCurl($subpageUrl, $timeout);
                            if ($subpageHtml) {
                                $pageData = $this->extractDetailsFromHtml($subpageHtml, $lead->website);
                                $contactPagesData[] = $pageData;
                            }
                        } catch (Exception $e) {
                            Log::warning("Subpage scan failed for $subpageUrl: " . $e->getMessage());
                        }
                    }
                    
                    // Strip tags for AI context
                    $textContext = strip_tags($htmlContent);
                    $textContext = preg_replace('/\s+/', ' ', $textContext);
                    $backgroundText = substr($textContext, 0, 2000);
                } else {
                    $backgroundText = "Failed to scan website. Server returned non-200 status or request timed out.";
                }
            } else {
                $backgroundText = "No valid website provided.";
            }

            // 2. Merge Details
            $allEmails = $homepageData['emails'] ?? [];
            $allPhones = $homepageData['phone'] ? [$homepageData['phone']] : [];
            $socials = [
                'facebook' => $homepageData['facebook_url'] ?? null,
                'instagram' => $homepageData['instagram_url'] ?? null,
                'linkedin' => $homepageData['linkedin_url'] ?? null,
                'whatsapp' => $homepageData['whatsapp_url'] ?? null,
                'youtube' => $homepageData['youtube_url'] ?? null,
                'contact_page_url' => $homepageData['contact_page_url'] ?? null,
            ];

            foreach ($contactPagesData as $pageData) {
                if (!empty($pageData['emails'])) {
                    $allEmails = array_merge($allEmails, $pageData['emails']);
                }
                if ($pageData['phone']) {
                    $allPhones[] = $pageData['phone'];
                }
                foreach (['facebook', 'instagram', 'linkedin', 'whatsapp', 'youtube', 'contact_page_url'] as $key) {
                    $valKey = $key . '_url';
                    if (empty($socials[$key]) && !empty($pageData[$valKey])) {
                        $socials[$key] = $pageData[$valKey];
                    }
                }
            }

            $emails = array_unique(array_filter($allEmails));
            $phones = array_unique(array_filter($allPhones));

            // Set Primary Lead Email
            if (!empty($emails)) {
                $lead->email = reset($emails);
                $lead->status = 'Email Found';
            } else {
                $lead->email = $lead->email ?: 'N/A';
            }

            $lead->phone = $lead->phone ?: (!empty($phones) ? reset($phones) : null);
            $lead->save();

            // Save Social Links
            foreach ($socials as $platform => $url) {
                if ($url) {
                    LeadSocial::updateOrCreate([
                        'lead_id' => $lead->id,
                        'platform' => $platform
                    ], [
                        'url' => $url
                    ]);
                }
            }

            // Save website phone & tech as socials or attributes
            $websitePhone = !empty($phones) ? reset($phones) : null;
            if ($websitePhone) {
                LeadSocial::updateOrCreate([
                    'lead_id' => $lead->id,
                    'platform' => 'website_phone'
                ], [
                    'url' => $websitePhone
                ]);
            }

            $techName = $techDetected ?: 'Custom HTML/JS';
            LeadSocial::updateOrCreate([
                'lead_id' => $lead->id,
                'platform' => 'website_tech'
            ], [
                'url' => $techName
            ]);

            // 3. Lead Score Calculation
            $score = 45;
            if ($lead->website && filter_var($lead->website, FILTER_VALIDATE_URL)) {
                $score += 10;
            }
            if ($lead->email && $lead->email !== 'N/A') {
                $score += 15;
            }
            if ($socials['facebook'] || $socials['instagram'] || $socials['linkedin'] || $socials['youtube']) {
                $score += 10;
            }
            if ($lead->rating) {
                if ($lead->rating >= 4.5) {
                    $score += 10;
                } elseif ($lead->rating < 4.0) {
                    $score -= 10;
                }
            }
            if ($lead->reviews_count > 50) {
                $score += 5;
            }
            if ($lead->phone || $websitePhone) {
                $score += 10;
            }
            if ($techName !== 'Custom HTML/JS') {
                $score += 5;
            }
            $lead->lead_score = max(0, min(100, $score));
            $lead->save();

            // 4. AI Audit
            $config = [];
            $aiDriver = $this->getAIDriver($lead->workspace_id, $config);
            $aiResult = null;

            if ($aiDriver) {
                try {
                    $leadData = [
                        'name' => $lead->name,
                        'address' => $lead->address,
                        'rating' => $lead->rating,
                        'reviews_count' => $lead->reviews_count,
                        'website' => $lead->website,
                        'emails' => $emails,
                        'socials' => $socials,
                        'website_tech' => $techName,
                        'background' => $backgroundText,
                    ];

                    $aiResult = $aiDriver->audit($leadData, $config);
                } catch (Exception $e) {
                    Log::error("Enrichment AI Call failed: " . $e->getMessage());
                }
            }

            // Fallback rules-based audit if AI failed or not configured
            if (empty($aiResult)) {
                $aiResult = $this->generateSimulatedAudit($lead, $techName, $socials, $websitePhone);
            }

            // Save lead audit details
            LeadAudit::updateOrCreate([
                'lead_id' => $lead->id
            ], [
                'strengths' => $aiResult['strengths'] ?? [],
                'gaps' => $aiResult['gaps'] ?? [],
                'suggestions' => $aiResult['suggestions'] ?? [],
                'cold_email_pitch' => $aiResult['cold_email_pitch'] ?? null,
                'background' => $backgroundText,
            ]);

            $lead->status = 'AI Audited';
            $lead->save();

            LeadActivity::create([
                'lead_id' => $lead->id,
                'activity_type' => 'scanned',
                'description' => "Enrichment complete. Score: {$lead->lead_score}, Tech: {$techName}.",
            ]);

        } catch (Exception $e) {
            $lead->update([
                'status' => 'Failed'
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'activity_type' => 'scanned',
                'description' => "Enrichment failed: " . $e->getMessage(),
            ]);

            Log::error("Lead enrichment failed for lead {$lead->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve AI driver based on workspace integration settings.
     */
    protected function getAIDriver(int $workspaceId, array &$config): ?AIProviderInterface
    {
        $providerSetting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('key', 'ai_provider')
            ->first();

        $apiKeySetting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('key', 'ai_api_key')
            ->first();

        $modelSetting = IntegrationSetting::where('workspace_id', $workspaceId)
            ->where('key', 'ai_model')
            ->first();

        $provider = $providerSetting ? strtolower($providerSetting->value) : 'openai';
        $apiKey = $apiKeySetting ? $apiKeySetting->value : env('OPENAI_API_KEY');
        $model = $modelSetting ? $modelSetting->value : null;

        $config = [
            'api_key' => $apiKey,
            'model' => $model,
        ];

        if (empty($apiKey)) {
            // Also fall back to Gemini key if gemini provider
            if ($provider === 'gemini') {
                $config['api_key'] = env('GEMINI_API_KEY');
            } else {
                $config['api_key'] = env('OPENAI_API_KEY');
            }
        }

        if (empty($config['api_key'])) {
            return null;
        }

        if ($provider === 'openai') {
            if (empty($config['model'])) {
                $config['model'] = 'gpt-4o-mini';
            }
            return app(\App\Services\Lead\Integration\Drivers\OpenAIDriver::class);
        } elseif ($provider === 'gemini') {
            if (empty($config['model'])) {
                $config['model'] = 'gemini-1.5-flash';
            }
            return app(\App\Services\Lead\Integration\Drivers\GeminiDriver::class);
        }

        return null;
    }

    /**
     * Bypass firewall blocks using raw PHP cURL with browser emulation & auto-decompression
     */
    protected function fetchUrlWithCurl(string $url, int $timeoutSeconds = 15): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Automatically decode GZIP, Deflate, Brotli, etc.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 200) {
            return $html;
        }
        
        Log::warning("cURL fetch request failed for URL $url. Code: $statusCode");
        return null;
    }

    /**
     * Parse HTML using regex patterns to extract email, phone, and socials
     */
    protected function extractDetailsFromHtml(string $html, string $baseUrl): array
    {
        $details = [
            'emails' => [],
            'phone' => null,
            'facebook_url' => null,
            'instagram_url' => null,
            'linkedin_url' => null,
            'whatsapp_url' => null,
            'youtube_url' => null,
            'contact_page_url' => null,
        ];

        // 1. Email Extraction
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}/', $html, $matches)) {
            $emails = array_unique($matches[0]);
            $details['emails'] = array_filter($emails, function($email) {
                return !preg_match('/\.(png|jpg|jpeg|gif|webp|svg|css|js|woff|woff2)$/i', $email);
            });
        }

        // 2. Phone Extraction
        if (preg_match('/href="tel:([^"]+)"/i', $html, $match)) {
            $details['phone'] = trim(urldecode($match[1]));
        } else {
            if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', strip_tags($html), $match)) {
                $details['phone'] = trim($match[0]);
            }
        }

        // 3. Social Media Links
        if (preg_match('/https?:\/\/(?:www\.)?(?:facebook\.com|fb\.com|fb\.me)\/[a-zA-Z0-9._-]+/i', $html, $match)) {
            $details['facebook_url'] = $match[0];
        }
        if (preg_match('/https?:\/\/(?:www\.)?instagram\.com\/[a-zA-Z0-9._-]+/i', $html, $match)) {
            $details['instagram_url'] = $match[0];
        }
        if (preg_match('/https?:\/\/(?:www\.)?linkedin\.com\/(?:in|company)\/[a-zA-Z0-9._-]+/i', $html, $match)) {
            $details['linkedin_url'] = $match[0];
        }
        if (preg_match('/https?:\/\/(?:wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com|whatsapp\.com\/[send|message])\/\+?[0-9a-zA-Z._-]+/i', $html, $match)) {
            $details['whatsapp_url'] = $match[0];
        }
        if (preg_match('/https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[a-zA-Z0-9._-]+/i', $html, $match)) {
            $details['youtube_url'] = $match[0];
        }

        // 4. Contact Page Extraction
        if (preg_match('/href="([^"]*(?:contact|iletisim|bize-ulasin|contact-us)[^"]*)"/i', $html, $match)) {
            $contactUrl = $match[1];
            $details['contact_page_url'] = $this->resolveAbsoluteUrl($contactUrl, $baseUrl);
        }

        return $details;
    }

    /**
     * Find contact links (contact, contact-us, about, about-us) on the page HTML
     */
    protected function findContactSubpages(string $html, string $baseUrl): array
    {
        $urls = [];
        if (preg_match_all('/href="([^"]*(?:contact|about|iletisim|bize-ulasin)[^"]*)"/i', $html, $matches)) {
            foreach (array_unique($matches[1]) as $rawUrl) {
                if (str_starts_with($rawUrl, '#') || preg_match('/\.(pdf|jpg|png|zip)$/i', $rawUrl)) {
                    continue;
                }
                $resolved = $this->resolveAbsoluteUrl($rawUrl, $baseUrl);
                if ($resolved && filter_var($resolved, FILTER_VALIDATE_URL)) {
                    $urls[] = $resolved;
                }
            }
        }
        return array_slice(array_unique($urls), 0, 3);
    }

    /**
     * Helper to resolve relative path links to absolute URLs
     */
    protected function resolveAbsoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'http';
        $host = $parsedBase['host'] ?? '';
        
        $rootUrl = "$scheme://$host";

        if (str_starts_with($url, '/')) {
            return $rootUrl . $url;
        }

        $path = $parsedBase['path'] ?? '';
        $dir = dirname($path);
        $dir = $dir === '\\' || $dir === '/' ? '' : $dir;

        return $rootUrl . '/' . ltrim($dir . '/' . $url, '/');
    }

    /**
     * Detect technology signature from raw HTML body content
     */
    protected function detectTechnology(string $html): string
    {
        $techSignatures = [
            'WordPress' => ['/wp-content\//i', '/wp-includes\//i', '/wp-json\//i'],
            'Shopify' => ['/cdn\.shopify\.com/i', '/shopify-payment-button/i', '/\.myshopify\.com/i'],
            'Wix' => ['/wixsite\.com/i', '/wix-root/i', '/static\.wixstatic\.com/i'],
            'Squarespace' => ['/squarespace\.com/i', '/static1\.squarespace\.com/i', '/squarespace-headers/i'],
            'Webflow' => ['/webflow\.com/i', '/data-wf-page/i', '/wf-site/i'],
            'Shopware' => ['/shopware/i'],
            'Joomla' => ['/media\/system\/js/i', '/Joomla!/'],
            'Drupal' => ['/sites\/all\//i', '/Drupal/i'],
            'Laravel' => ['/XSRF-TOKEN/i', '/_token/i']
        ];

        foreach ($techSignatures as $tech => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    return $tech;
                }
            }
        }

        if (preg_match('/bootstrap\.min\.css/i', $html) || preg_match('/bootstrap\.min\.js/i', $html)) {
            return 'Bootstrap Site';
        }

        return 'Custom HTML/JS';
    }

    /**
     * Generate rules-based fallback audit
     */
    protected function generateSimulatedAudit(Lead $lead, string $tech, array $socials, ?string $webPhone): array
    {
        $strengths = ["Has Google Maps visibility"];
        $gaps = [];
        $suggestions = [];

        if ($lead->phone || $webPhone) {
            $strengths[] = "Active contact number listed";
        } else {
            $gaps[] = "No direct phone number listed";
            $suggestions[] = "Add a call-to-action button or phone number to boost bookings";
        }

        if ($lead->rating) {
            if ($lead->rating >= 4.5) {
                $strengths[] = "Strong reputation with a rating of {$lead->rating}";
            } else {
                $gaps[] = "Public rating is {$lead->rating}, which could deter customers";
                $suggestions[] = "Implement a reviews generation strategy to boost rating above 4.5";
            }
        }

        if ($lead->website) {
            $strengths[] = "Active website link built with {$tech}";
            if (empty($lead->email) || $lead->email === 'N/A') {
                $gaps[] = "No public email found on the website";
                $suggestions[] = "Add a clear email or contact form to reduce client conversion friction";
            }
        } else {
            $gaps[] = "Missing official website link on Google Maps";
            $suggestions[] = "Create an optimized landing page to capture high-intent leads from Google Maps";
        }

        $socialsCount = 0;
        foreach (['facebook', 'instagram', 'linkedin', 'youtube'] as $key) {
            if (!empty($socials[$key])) $socialsCount++;
        }

        if ($socialsCount > 0) {
            $strengths[] = "Active social media channels connected";
        } else {
            $gaps[] = "No social media pages (Facebook, Instagram, LinkedIn) found on website";
            $suggestions[] = "Create and link social handles to foster brand trust and organic traffic";
        }

        if ($lead->reviews_count < 10) {
            $gaps[] = "Very low review count ({$lead->reviews_count}) compared to competitors";
            $suggestions[] = "Encourage satisfied customers to leave feedback on Google";
        }

        $pitch = "I noticed {$lead->name} has a " . ($lead->rating ? "{$lead->rating} star rating" : "great listing") . " on Google Maps, and I'd love to help you scale your online customer bookings.";

        return [
            'strengths' => $strengths,
            'gaps' => $gaps,
            'suggestions' => $suggestions,
            'cold_email_pitch' => $pitch
        ];
    }
}
