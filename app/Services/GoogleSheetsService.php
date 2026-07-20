<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Goal;
use App\Models\ProjectStateAudit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    /**
     * Get OAuth 2.0 Access Token using the stored refresh token.
     */
    protected function getAccessToken(): ?string
    {
        $refreshToken = env('GOOGLE_SHEETS_REFRESH_TOKEN');
        $clientId = env('GOOGLE_SHEETS_CLIENT_ID');
        $clientSecret = env('GOOGLE_SHEETS_CLIENT_SECRET');

        if (empty($refreshToken) || empty($clientId) || empty($clientSecret)) {
            Log::warning("Google Sheets: Missing client credentials or refresh token in .env.");
            return null;
        }

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token'
        ]);

        if (!$response->successful()) {
            Log::error("Google Sheets OAuth Token Refresh Failed: " . $response->body());
            return null;
        }

        return $response->json()['access_token'] ?? null;
    }

    /**
     * Read cell values from a range using API Key (read-only).
     */
    public function readSheet(string $range): array
    {
        $apiKey = env('GOOGLE_SHEETS_API_KEY');
        $spreadsheetId = env('GOOGLE_SHEETS_SPREADSHEET_ID');
        if (empty($apiKey) || empty($spreadsheetId)) {
            Log::warning("Google Sheets: API Key or Spreadsheet ID is not set in .env.");
            return [];
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}?key={$apiKey}";
        $response = Http::get($url);
        return $response->json()['values'] ?? [];
    }

    /**
     * Append a row of data to the Sheet worksheet (requires Service Account).
     */
    public function appendRow(array $row): bool
    {
        $spreadsheetId = env('GOOGLE_SHEETS_SPREADSHEET_ID');
        $sheetName = env('GOOGLE_SHEETS_SHEET_NAME', 'Sheet1');
        $accessToken = $this->getAccessToken();

        if (empty($spreadsheetId) || empty($accessToken)) {
            Log::warning("Google Sheets: Missing spreadsheet ID or Service Account Access Token.");
            return false;
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$sheetName}!A1:append?valueInputOption=USER_ENTERED";
        
        $response = Http::withToken($accessToken)->post($url, [
            'values' => [$row]
        ]);

        return $response->successful();
    }

    /**
     * Import goal progress values from Sheet to Database (if sync mode permits).
     */
    public function syncFromSheet(Project $project): void
    {
        $syncMode = env('GOOGLE_SHEETS_SYNC_MODE', 'export');
        if ($syncMode === 'export') {
            return;
        }

        $sheetName = env('GOOGLE_SHEETS_SHEET_NAME', 'Sheet1');
        $rows = $this->readSheet("{$sheetName}!A1:Z100");
        if (empty($rows)) {
            return;
        }

        $goals = $project->goals()->where('status', 'active')->get();
        foreach ($goals as $goal) {
            foreach ($rows as $row) {
                foreach ($row as $index => $cellValue) {
                    if (strcasecmp(trim($cellValue), trim($goal->title)) === 0) {
                        $nextCell = $row[$index + 1] ?? null;
                        if ($nextCell !== null && is_numeric(trim($nextCell))) {
                            $sheetValue = intval(trim($nextCell));
                            
                            if ($sheetValue !== $goal->current_value) {
                                $oldVal = $goal->current_value;
                                $goal->current_value = $sheetValue;
                                $goal->save();

                                // Log to audit trail
                                ProjectStateAudit::create([
                                    'project_id'      => $project->id,
                                    'goal_title'      => $goal->title,
                                    'operation'       => 'set_total',
                                    'value'           => $sheetValue,
                                    'previous_value'  => $oldVal,
                                    'new_value'       => $sheetValue,
                                    'entity'          => $goal->unit,
                                    'router'          => 'google_sheets_import',
                                    'confidence'      => 1.0,
                                ]);

                                Log::info("Synced Goal \"{$goal->title}\" from Google Sheets: {$oldVal} -> {$sheetValue}");
                            }
                        }
                    }
                }
            }
        }
    }
}
