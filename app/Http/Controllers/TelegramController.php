<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function __construct(private TelegramService $telegram)
    {
    }

    /**
     * Telegram webhook: receives updates from the bot.
     * Public endpoint, no auth required.
     */
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();
        $message = $update['message'] ?? null;

        if (!$message || empty($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text']);
        $firstName = $message['from']['first_name'] ?? 'Usuario';

        if (str_starts_with($text, '/start')) {
            $token = trim(str_replace('/start', '', $text));

            if (!empty($token)) {
                $user = User::where('telegram_link_token', $token)->first();

                if ($user) {
                    $user->update([
                        'telegram_chat_id' => $chatId,
                        'telegram_link_token' => null,
                    ]);

                    $this->telegram->sendMessage($chatId,
                        "✅ <b>Conectado!</b>\n\nHola {$firstName}, tu cuenta de GridBot fue vinculada exitosamente.\n\nVas a recibir notificaciones cuando el agente AI tome acciones en tus bots."
                    );

                    return response()->json(['ok' => true]);
                }
            }

            $this->telegram->sendMessage($chatId,
                "👋 <b>Hola {$firstName}!</b>\n\nPara vincular tu cuenta, andá a <b>Perfil → Telegram</b> en GridBot y seguí las instrucciones.\n\nTu Chat ID: <code>{$chatId}</code>"
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Generate a link token for the current user.
     */
    public function generateLinkToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(32);

        $user->update(['telegram_link_token' => $token]);

        $botToken = config('services.telegram.bot_token');
        $botUsername = null;

        if ($botToken) {
            try {
                $info = \Illuminate\Support\Facades\Http::get(
                    "https://api.telegram.org/bot{$botToken}/getMe"
                )->json();
                $botUsername = $info['result']['username'] ?? null;
            } catch (\Exception $e) {
                // ignore
            }
        }

        return response()->json([
            'token' => $token,
            'bot_username' => $botUsername,
            'deep_link' => $botUsername
                ? "https://t.me/{$botUsername}?start={$token}"
                : null,
        ]);
    }

    /**
     * Disconnect Telegram.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $request->user()->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Send a test message.
     */
    public function testMessage(Request $request): JsonResponse
    {
        $chatId = $request->user()->telegram_chat_id;

        if (!$chatId) {
            return response()->json(['error' => 'Telegram no vinculado'], 422);
        }

        $sent = $this->telegram->sendMessage($chatId,
            "🔔 <b>Mensaje de prueba</b>\n\nLas notificaciones de GridBot están funcionando correctamente."
        );

        return response()->json(['success' => $sent]);
    }

    /**
     * Setup the webhook (admin utility).
     */
    public function setupWebhook(Request $request): JsonResponse
    {
        $url = rtrim(config('app.url'), '/') . '/api/telegram/webhook';
        $result = $this->telegram->setWebhook($url);

        return response()->json($result);
    }
}
