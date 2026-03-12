<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAgentApiController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/ai-agent/conversations
     *
     * List recent conversations with summary, actions taken, and stats.
     * ?bot_id=N  — filter by bot
     * ?limit=N   — max results (default 20, max 100)
     * ?status=X  — filter: completed, error, running
     */
    public function conversations(Request $request): JsonResponse
    {
        $botIds = Bot::where('user_id', $request->user()->id)->pluck('id');

        $query = AiConversation::whereIn('bot_id', $botIds)
            ->with(['bot:id,name,symbol', 'actionLogs:id,conversation_id,action,source,details'])
            ->withCount('messages')
            ->latest('ended_at');

        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->input('bot_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $limit = min((int) $request->input('limit', 20), 100);

        $conversations = $query->limit($limit)->get()->map(fn (AiConversation $c) => [
            'id'               => $c->id,
            'bot_id'           => $c->bot_id,
            'bot_name'         => $c->bot?->name,
            'bot_symbol'       => $c->bot?->symbol,
            'status'           => $c->status,
            'trigger'          => $c->trigger,
            'model'            => $c->model,
            'summary'          => $c->summary,
            'analysis'         => $c->analysis,
            'actions_taken'    => $c->actions_taken,
            'total_tokens'     => (int) $c->total_tokens,
            'total_tool_calls' => (int) $c->total_tool_calls,
            'total_messages'   => (int) $c->messages_count,
            'duration_ms'      => (int) $c->duration_ms,
            'action_logs'      => $c->actionLogs->map(fn (BotActionLog $a) => [
                'action'  => $a->action,
                'source'  => $a->source,
                'details' => $a->details,
            ])->values(),
            'started_at'       => $c->started_at?->toIso8601String(),
            'ended_at'         => $c->ended_at?->toIso8601String(),
        ]);

        return $this->successResponse($conversations, 'AI conversations retrieved');
    }

    /**
     * GET /api/v1/ai-agent/conversations/{conversation}
     *
     * Full conversation detail including every message and tool call.
     */
    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $botIds = Bot::where('user_id', $request->user()->id)->pluck('id');
        abort_if(! $botIds->contains($conversation->bot_id), 403, 'Forbidden');

        $conversation->load(['bot:id,name,symbol', 'messages', 'actionLogs']);

        return $this->successResponse([
            'id'               => $conversation->id,
            'bot_id'           => $conversation->bot_id,
            'bot_name'         => $conversation->bot?->name,
            'bot_symbol'       => $conversation->bot?->symbol,
            'status'           => $conversation->status,
            'trigger'          => $conversation->trigger,
            'model'            => $conversation->model,
            'summary'          => $conversation->summary,
            'analysis'         => $conversation->analysis,
            'actions_taken'    => $conversation->actions_taken,
            'total_tokens'     => (int) $conversation->total_tokens,
            'total_tool_calls' => (int) $conversation->total_tool_calls,
            'duration_ms'      => (int) $conversation->duration_ms,
            'started_at'       => $conversation->started_at?->toIso8601String(),
            'ended_at'         => $conversation->ended_at?->toIso8601String(),
            'messages'         => $conversation->messages->map(fn ($m) => [
                'id'          => $m->id,
                'role'        => $m->role,
                'content'     => $m->content,
                'tool_calls'  => $m->tool_calls,
                'tool_call_id'=> $m->tool_call_id,
                'tool_name'   => $m->tool_name,
                'tool_args'   => $m->tool_args,
                'tool_result' => $m->tool_result,
                'tokens'      => (int) $m->tokens,
                'created_at'  => $m->created_at?->toIso8601String(),
            ])->values(),
            'action_logs'      => $conversation->actionLogs->map(fn (BotActionLog $a) => [
                'id'           => $a->id,
                'action'       => $a->action,
                'source'       => $a->source,
                'details'      => $a->details,
                'before_state' => $a->before_state,
                'after_state'  => $a->after_state,
                'created_at'   => $a->created_at?->toIso8601String(),
            ])->values(),
        ], 'Conversation detail retrieved');
    }

    /**
     * GET /api/v1/ai-agent/actions
     *
     * List recent bot action logs (across all bots or filtered).
     * ?bot_id=N  — filter by bot
     * ?limit=N   — max results (default 30, max 100)
     */
    public function actions(Request $request): JsonResponse
    {
        $botIds = Bot::where('user_id', $request->user()->id)->pluck('id');

        $query = BotActionLog::whereIn('bot_id', $botIds)
            ->with(['bot:id,name,symbol', 'conversation:id,status,summary'])
            ->latest();

        if ($request->filled('bot_id')) {
            $query->where('bot_id', $request->input('bot_id'));
        }

        $limit = min((int) $request->input('limit', 30), 100);

        $actions = $query->limit($limit)->get()->map(fn (BotActionLog $a) => [
            'id'                   => $a->id,
            'bot_id'               => $a->bot_id,
            'bot_name'             => $a->bot?->name,
            'bot_symbol'           => $a->bot?->symbol,
            'conversation_id'      => $a->conversation_id,
            'conversation_summary' => $a->conversation?->summary,
            'action'               => $a->action,
            'source'               => $a->source,
            'details'              => $a->details,
            'before_state'         => $a->before_state,
            'after_state'          => $a->after_state,
            'created_at'           => $a->created_at?->toIso8601String(),
        ]);

        return $this->successResponse($actions, 'Action logs retrieved');
    }
}
