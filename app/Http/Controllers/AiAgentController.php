<?php

namespace App\Http\Controllers;

use App\Models\AiAgentLog;
use App\Models\AiConversation;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\Agent\AgentOrchestrator;
use App\Services\AiTradingAgent;
use Illuminate\Http\Request;
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

        $actionLogs = BotActionLog::with('bot')
            ->whereIn('bot_id', $botIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $quickAnalyses = AiAgentLog::with('bot')
            ->where(function ($q) use ($botIds) {
                $q->whereIn('bot_id', $botIds)->orWhereNull('bot_id');
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $stats = [
            'total_conversations' => AiConversation::whereIn('bot_id', $botIds)->count(),
            'total_tool_calls' => AiConversation::whereIn('bot_id', $botIds)->sum('total_tool_calls'),
            'total_actions' => BotActionLog::whereIn('bot_id', $botIds)->count(),
            'avg_duration' => round(AiConversation::whereIn('bot_id', $botIds)->avg('duration_ms') ?? 0),
            'total_quick_analyses' => AiAgentLog::count(),
        ];

        $userBots = Bot::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get(['id', 'symbol', 'name', 'status']);

        return Inertia::render('AiAgent/Index', [
            'conversations' => $conversations,
            'actionLogs' => $actionLogs,
            'quickAnalyses' => $quickAnalyses,
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
        $conversation = $orchestrator->consult($bot, 'manual');

        if ($conversation->status === 'completed') {
            return redirect()->route('ai-agent.conversation', $conversation)
                ->with('success', "Consulta completada: {$conversation->total_tool_calls} tools, " .
                    round($conversation->duration_ms / 1000, 1) . 's');
        }

        return back()->with('error', 'La consulta falló: ' . ($conversation->summary ?? 'Error desconocido'));
    }

    public function runQuickAnalysis(Request $request)
    {
        $request->validate([
            'bot_id' => 'required|exists:bots,id',
        ]);

        $bot = Bot::where('id', $request->bot_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $agent = app(AiTradingAgent::class);
        $log = $agent->analyzeBot($bot);

        if ($log) {
            return back()->with('success', "Análisis: {$log->signal} ({$log->confidence}%)");
        }

        return back()->with('error', 'No se pudo completar el análisis.');
    }
}
