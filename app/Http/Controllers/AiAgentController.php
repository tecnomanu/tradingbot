<?php

namespace App\Http\Controllers;

use App\Enums\AgentTrigger;
use App\Models\AiConversation;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\Agent\AgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class AiAgentController extends Controller
{
    public function index(Request $request)
    {
        $botIds = Bot::where('user_id', $request->user()->id)->pluck('id');

        $conversations = AiConversation::with('bot')
            ->whereIn('bot_id', $botIds)
            ->orderByDesc('created_at')
            ->paginate(20);

        $actionLogs = BotActionLog::with(['bot', 'user'])
            ->whereIn('bot_id', $botIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (BotActionLog $log) => [
                'id' => $log->id,
                'bot_id' => $log->bot_id,
                'conversation_id' => $log->conversation_id,
                'action' => $log->action,
                'source' => $log->source,
                'actor_label' => $log->actor_label,
                'details' => $log->details,
                'before_state' => $log->before_state,
                'after_state' => $log->after_state,
                'created_at' => $log->created_at->toIso8601String(),
                'created_at_fmt' => $log->created_at->format('d/m H:i'),
                'bot' => $log->bot ? ['id' => $log->bot->id, 'symbol' => $log->bot->symbol] : null,
            ]);

        $stats = [
            'total_conversations' => AiConversation::whereIn('bot_id', $botIds)->count(),
            'total_tool_calls' => AiConversation::whereIn('bot_id', $botIds)->sum('total_tool_calls'),
            'total_actions' => BotActionLog::whereIn('bot_id', $botIds)->count(),
            'avg_duration' => round(AiConversation::whereIn('bot_id', $botIds)->avg('duration_ms') ?? 0),
        ];

        $userBots = Bot::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'symbol', 'name', 'status']);

        return Inertia::render('AiAgent/Index', [
            'conversations' => $conversations,
            'actionLogs' => $actionLogs,
            'stats' => $stats,
            'userBots' => $userBots,
        ]);
    }

    public function showConversation(Request $request, AiConversation $conversation)
    {
        $botIds = Bot::where('user_id', $request->user()->id)->pluck('id');

        if (!$botIds->contains($conversation->bot_id)) {
            abort(403);
        }

        $conversation->load(['bot', 'messages', 'actionLogs']);

        return Inertia::render('AiAgent/Conversation', [
            'conversation' => $conversation,
        ]);
    }

    public function runConsultation(Request $request)
    {
        $request->validate([
            'bot_id' => 'required|exists:bots,id',
        ]);

        $bot = Bot::where('id', $request->bot_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $orchestrator = app(AgentOrchestrator::class);
        $conversation = $orchestrator->consult($bot, AgentTrigger::Manual);

        if ($conversation->status === 'completed') {
            return redirect()->route('ai-agent.conversation', $conversation)
                ->with('success', "Consulta completada: {$conversation->total_tool_calls} tools, " .
                    round($conversation->duration_ms / 1000, 1) . 's');
        }

        return back()->with('error', 'La consulta falló: ' . ($conversation->summary ?? 'Error desconocido'));
    }

    public function updateBotPrompts(Request $request, Bot $bot)
    {
        abort_unless($bot->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'ai_system_prompt' => 'nullable|string|max:5000',
            'ai_user_prompt' => 'nullable|string|max:2000',
            'ai_consultation_interval' => 'nullable|integer|in:5,10,15,30,60',
            'ai_notify_telegram' => 'nullable|boolean',
            'ai_notify_events' => 'nullable|array',
            'ai_notify_events.*' => 'string|in:grid_adjusted,bot_stopped,risk_guard_triggered,soft_guard_triggered,hard_guard_triggered,reentry_success,reentry_blocked,stop_loss_set,take_profit_set,position_closed,orders_cancelled',
        ]);

        $bot->update([
            'ai_system_prompt' => $data['ai_system_prompt'] ?: null,
            'ai_user_prompt' => $data['ai_user_prompt'] ?: null,
            'ai_consultation_interval' => $data['ai_consultation_interval'] ?? 15,
            'ai_notify_telegram' => $data['ai_notify_telegram'] ?? false,
            'ai_notify_events' => $data['ai_notify_events'] ?? null,
        ]);

        return back()->with('success', 'Configuración AI actualizada.');
    }

    public function testBotPrompts(Request $request, Bot $bot): JsonResponse
    {
        abort_unless($bot->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'ai_system_prompt' => 'nullable|string|max:5000',
            'ai_user_prompt' => 'nullable|string|max:2000',
        ]);

        $personality = $data['ai_system_prompt'] ?: AgentOrchestrator::defaultPersonality();
        $systemPrompt = $personality . "\n\n" . AgentOrchestrator::operationalPrompt();
        $userPrompt = $data['ai_user_prompt'] ?: AgentOrchestrator::defaultUserPrompt();

        $reviewPrompt = <<<REVIEW
You are an expert AI prompt engineer reviewing a trading bot agent configuration.

Analyze the following system prompt and first message that will be used by an LLM agent managing a grid trading bot. The agent has tools: get_bot_status, get_market_data, get_open_orders, get_filled_orders, get_binance_position, set_stop_loss, set_take_profit, cancel_all_orders, stop_bot, close_position, done.

--- SYSTEM PROMPT ---
{$systemPrompt}

--- FIRST MESSAGE (template, {bot_id}/{symbol}/{now} are replaced at runtime) ---
{$userPrompt}

--- BOT CONTEXT ---
Bot #{$bot->id} | Symbol: {$bot->symbol} | Side: {$bot->side} | Leverage: {$bot->leverage}x | Grid: {$bot->price_lower}-{$bot->price_upper} ({$bot->grid_count} levels)

Respond in Spanish. Provide a brief assessment (max 200 words):
1. **Alcance**: What this config covers and what it misses
2. **Fortalezas**: What's good
3. **Debilidades**: Potential issues or gaps
4. **Mejoras**: Concrete suggestions to improve
REVIEW;

        try {
            $apiUrl = config('services.ai.url') ?: 'https://api.groq.com/openai/v1/chat/completions';
            $apiKey = config('services.ai.key');
            $model = config('services.ai.model') ?: 'qwen/qwen3-32b';

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($apiUrl, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $reviewPrompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'API error: ' . $response->status()], 500);
            }

            $content = $response->json('choices.0.message.content') ?? '';
            $content = trim(preg_replace('/<think>.*?(<\/think>|$)/s', '', $content));

            return response()->json(['review' => $content]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
