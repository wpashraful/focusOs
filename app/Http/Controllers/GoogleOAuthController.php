<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleOAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth 2.0 server.
     */
    public function redirectToGoogle()
    {
        $clientId = env('GOOGLE_SHEETS_CLIENT_ID');
        $redirectUri = route('google.callback');

        if (empty($clientId)) {
            return redirect('/chat')->with('error', 'Google Sheets Client ID is not configured in .env file.');
        }

        $query = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/spreadsheets',
            'access_type'   => 'offline',
            'prompt'        => 'consent'
        ]);

        return redirect("https://accounts.google.com/o/oauth2/auth?{$query}");
    }

    /**
     * Handle the callback redirection from Google.
     */
    public function handleCallback(Request $request)
    {
        $code = $request->get('code');
        if (empty($code)) {
            return redirect('/chat')->with('error', 'Authorization code not returned from Google OAuth.');
        }

        $clientId = env('GOOGLE_SHEETS_CLIENT_ID');
        $clientSecret = env('GOOGLE_SHEETS_CLIENT_SECRET');
        $redirectUri = route('google.callback');

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'code'          => $code
        ]);

        if (!$response->successful()) {
            Log::error("Google Sheets OAuth Token Exchange Failed: " . $response->body());
            return redirect('/chat')->with('error', 'Failed to exchange authorization code for tokens.');
        }

        $tokens = $response->json();
        $refreshToken = $tokens['refresh_token'] ?? null;

        if (empty($refreshToken)) {
            Log::warning("Google Sheets OAuth: No refresh token returned. Make sure to prompt consent.");
            return redirect('/chat')->with('error', 'No refresh token returned. Try connecting again.');
        }

        // Store refresh token in the .env file
        $this->updateEnvFile('GOOGLE_SHEETS_REFRESH_TOKEN', $refreshToken);

        return redirect('/chat')->with('success', 'Google Sheets connected successfully!');
    }

    /**
     * Update/append a key-value pair in the .env file.
     */
    protected function updateEnvFile(string $key, string $value): void
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            $content = file_get_contents($path);

            if (preg_match("/^{$key}=/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                $content = rtrim($content) . "\n{$key}={$value}\n";
            }

            file_put_contents($path, $content);
        }
    }
}
