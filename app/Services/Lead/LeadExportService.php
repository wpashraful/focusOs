<?php

namespace App\Services\Lead;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\IntegrationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LeadExportService
{
    /**
     * Export leads collection to CSV format string.
     */
    public function exportToCsv(Collection $leads): string
    {
        $output = fopen('php://temp', 'r+');

        // CSV Headers
        fputcsv($output, [
            'Name',
            'Website',
            'Phone',
            'Email',
            'Rating',
            'Reviews Count',
            'Address',
            'Lead Score',
            'Root Keyword',
            'Keyword Variation',
            'Country',
            'Location City',
            'Location State',
            'Zip Code',
            'Status',
            'Created At'
        ]);

        foreach ($leads as $lead) {
            $session = $lead->session;
            $coverage = $session ? $session->coverage : null;
            $variation = $coverage ? $coverage->variation : null;
            $rootKeyword = $variation ? $variation->rootKeyword : null;
            $location = $coverage ? $coverage->location : null;

            $rootKeywordVal = $rootKeyword ? $rootKeyword->keyword : '';
            $variationVal = $variation ? $variation->keyword : '';
            $countryVal = $location ? $location->country : '';
            $cityVal = $location ? $location->city : '';
            $stateVal = $location ? $location->state : '';
            $zipCodeVal = $this->extractZipCode($lead->address);

            fputcsv($output, [
                $lead->name,
                $lead->website ?? '',
                $lead->phone ?? '',
                $lead->email ?? 'N/A',
                $lead->rating ?? '',
                $lead->reviews_count,
                $lead->address ?? '',
                $lead->lead_score,
                $rootKeywordVal,
                $variationVal,
                $countryVal,
                $cityVal,
                $stateVal,
                $zipCodeVal,
                $lead->status,
                $lead->created_at ? $lead->created_at->toDateTimeString() : ''
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Extract Zip/Postal Code from raw address string.
     */
    private function extractZipCode(?string $address): string
    {
        if (empty($address)) {
            return '';
        }

        // US Zip Code: 5 digits, optional hyphen and 4 digits (e.g. 33130 or 33130-1234)
        if (preg_match('/\b\d{5}(?:-\d{4})?\b/', $address, $matches)) {
            return $matches[0];
        }

        // UK Postal Code (e.g. SW1A 1AA)
        if (preg_match('/\b[A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}\b/i', $address, $matches)) {
            return strtoupper($matches[0]);
        }

        // Canada Postal Code (e.g. K1A 0B1)
        if (preg_match('/\b[A-Z]\d[A-Z] \d[A-Z]\d\b/i', $address, $matches)) {
            return strtoupper($matches[0]);
        }

        return '';
    }

    /**
     * Mirror sync a lead to Google Sheets.
     */
    public function syncToGoogleSheets(Lead $lead): bool
    {
        $spreadsheetIdSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)
            ->where('key', 'google_sheets_spreadsheet_id')
            ->first();

        $sheetNameSetting = IntegrationSetting::where('workspace_id', $lead->workspace_id)
            ->where('key', 'google_sheets_sheet_name')
            ->first();

        $spreadsheetId = $spreadsheetIdSetting ? $spreadsheetIdSetting->value : env('GOOGLE_SHEETS_SPREADSHEET_ID');
        $sheetName = $sheetNameSetting ? $sheetNameSetting->value : env('GOOGLE_SHEETS_SHEET_NAME', 'Sheet1');

        if (empty($spreadsheetId)) {
            Log::warning("Google Sheets: Spreadsheet ID not set in Integration Settings for workspace {$lead->workspace_id}. Sync skipped.");
            return false;
        }

        $config = [
            'spreadsheet_id' => $spreadsheetId,
            'sheet_name' => $sheetName
        ];

        // Resolve Google Sheets Driver via container injection
        $sheetsDriver = app(\App\Services\Lead\Integration\Drivers\GoogleSheetsDriver::class);

        $leadData = [
            'name' => $lead->name,
            'website' => $lead->website,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'rating' => $lead->rating,
            'reviews_count' => $lead->reviews_count,
            'address' => $lead->address,
            'lead_score' => $lead->lead_score,
            'status' => $lead->status
        ];

        $success = $sheetsDriver->sync($leadData, $config);

        if ($success) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'activity_type' => 'status_updated',
                'description' => 'Lead mirrored successfully to Google Sheets.'
            ]);
        }

        return $success;
    }
}
