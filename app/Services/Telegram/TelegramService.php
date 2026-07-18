<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;
    protected string $apiBase = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN', '');
    }

    /**
     * Send message to specific Telegram chat ID.
     */
    public function sendMessage(int $chatId, string $text, array $extra = []): bool
    {
        if (empty($this->token)) {
            Log::warning("Telegram sendMessage aborted: TELEGRAM_BOT_TOKEN is not configured.");
            return false;
        }

        $url = $this->apiBase . $this->token . '/sendMessage';

        try {
            $payload = array_merge([
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ], $extra);

            $response = Http::timeout(10)->post($url, $payload);

            if ($response->failed()) {
                Log::error("Telegram API sendMessage failed: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Telegram sendMessage Exception: " . $e->getMessage());
            return false;
        }
    }
}
