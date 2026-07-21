<?php

namespace App\Services\Lead\Integration\Drivers;

use App\Services\Lead\Integration\Contracts\SheetsProviderInterface;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleSheetsDriver implements SheetsProviderInterface
{
    protected $sheetsService;

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }

    public function sync(array $leadData, array $config): bool
    {
        try {
            $spreadsheetId = $config['spreadsheet_id'] ?? env('GOOGLE_SHEETS_SPREADSHEET_ID');
            $sheetName = $config['sheet_name'] ?? env('GOOGLE_SHEETS_SHEET_NAME', 'Sheet1');

            if (empty($spreadsheetId)) {
                Log::warning("Google Sheets: Spreadsheet ID not configured for sync.");
                return false;
            }

            // Structure row values
            $row = [
                $leadData['name'] ?? '',
                $leadData['website'] ?? '',
                $leadData['phone'] ?? '',
                $leadData['email'] ?? 'N/A',
                $leadData['rating'] ?? '',
                $leadData['reviews_count'] ?? 0,
                $leadData['address'] ?? '',
                $leadData['lead_score'] ?? 0,
                $leadData['status'] ?? 'Imported',
                date('Y-m-d H:i:s')
            ];

            // Re-bind env configuration temporarily if dynamic configs are passed
            if (!empty($config['spreadsheet_id'])) {
                config(['services.google_sheets.spreadsheet_id' => $spreadsheetId]);
            }
            if (!empty($config['sheet_name'])) {
                config(['services.google_sheets.sheet_name' => $sheetName]);
            }

            return $this->sheetsService->appendRow($row);
        } catch (Exception $e) {
            Log::error("Google Sheets Sync failed: " . $e->getMessage());
            return false;
        }
    }
}
