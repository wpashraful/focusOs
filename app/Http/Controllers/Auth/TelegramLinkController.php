<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TelegramLinkCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TelegramLinkController extends Controller
{
    /**
     * Show settings page and generate a fresh link code.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Clean up old codes for this user
        TelegramLinkCode::where('user_id', $user->id)->delete();

        // Generate new code valid for 10 minutes
        $code = TelegramLinkCode::create([
            'user_id'    => $user->id,
            'code'       => strtoupper(Str::random(8)),
            'expires_at' => now()->addMinutes(10),
        ]);

        return Inertia::render('Settings/Telegram', [
            'linkCode'         => $code->code,
            'telegramLinked'   => ! is_null($user->telegram_id),
            'telegramUsername' => $user->telegram_username,
        ]);
    }

    /**
     * Confirm a link code sent from Telegram bot.
     * Called by the Telegram bot webhook, not the user directly.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code'              => ['required', 'string'],
            'telegram_id'       => ['required', 'string'],
            'telegram_username' => ['nullable', 'string'],
        ]);

        $linkCode = TelegramLinkCode::where('code', strtoupper($request->code))
            ->where('expires_at', '>', now())
            ->first();

        if (! $linkCode) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.'], 422);
        }

        $user = $linkCode->user;
        $user->update([
            'telegram_id'       => $request->telegram_id,
            'telegram_username' => $request->telegram_username,
        ]);

        $linkCode->delete();

        return response()->json([
            'success' => true,
            'message' => "✅ Account linked! Welcome, {$user->name}. You can now chat with FocusOS here.",
        ]);
    }

    /**
     * Unlink Telegram from the account.
     */
    public function unlink(Request $request)
    {
        $request->user()->update([
            'telegram_id'       => null,
            'telegram_username' => null,
        ]);

        return back()->with('success', 'Telegram account unlinked.');
    }
}
