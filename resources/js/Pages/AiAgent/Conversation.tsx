import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    Brain,
    CheckCircle2,
    ChevronDown,
    Clock,
    MessageSquare,
    Shield,
    Wrench,
    XCircle,
} from "lucide-react";
import { useState } from "react";

interface Message {
    id: number;
    role: string;
    content: string | null;
    tool_calls: any[] | null;
    tool_call_id: string | null;
    tool_name: string | null;
    tool_args: Record<string, any> | null;
    tool_result: Record<string, any> | null;
    tokens: number | null;
    created_at: string;
}

interface ActionLog {
    id: number;
    action: string;
    source: string;
    details: Record<string, any> | null;
    created_at: string;
}

interface ConversationData {
    id: number;
    bot_id: number;
    status: string;
    trigger: string;
    model: string | null;
    summary: string | null;
    analysis: string | null;
    total_tokens: number;
    total_tool_calls: number;
    total_messages: number;
    duration_ms: number | null;
    actions_taken: string[] | null;
    created_at: string;
    bot: { id: number; symbol: string; name: string } | null;
    messages: Message[];
    action_logs: ActionLog[];
}

import { actionLabels, getActionLabel } from "@/utils/activityLabels";

function formatArgsCompact(args: Record<string, any>): string {
    if (!args || Object.keys(args).length === 0) return "";
    return Object.entries(args)
        .map(([k, v]) => `${k}: ${typeof v === "object" ? JSON.stringify(v) : v}`)
        .join(" · ");
}

function ToolResultDisplay({
    result,
    isError,
}: {
    result: Record<string, any>;
    isError: boolean;
}) {
    const [open, setOpen] = useState(false);
    const str = JSON.stringify(result, null, 2);
    const isLong = str.length > 150;

    if (isError) {
        const errMsg = result?.error ?? (typeof result?.message === "string" ? result.message : str);
        return (
            <div className="mt-2 flex items-start gap-2 rounded-md border border-red-500/30 bg-red-500/10 p-2">
                <XCircle className="h-4 w-4 shrink-0 text-red-400" />
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-medium text-red-400">Error</p>
                    <p className="text-xs text-red-300/90 break-words">{String(errMsg)}</p>
                </div>
            </div>
        );
    }

    if (!isLong) {
        return (
            <div className="mt-2 flex items-start gap-2 rounded-md border border-emerald-500/20 bg-emerald-500/5 p-2">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-400" />
                <pre className="flex-1 overflow-auto text-xs text-muted-foreground">{str}</pre>
            </div>
        );
    }

    return (
        <div className="mt-2">
            <button
                onClick={() => setOpen(!open)}
                className="flex items-center gap-2 rounded-md border border-emerald-500/20 bg-emerald-500/5 p-2 text-left w-full hover:bg-emerald-500/10 transition-colors"
            >
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-400" />
                <span className="text-xs font-medium text-emerald-400">Éxito</span>
                <ChevronDown className={`h-3 w-3 ml-auto transition-transform ${open ? "rotate-0" : "-rotate-90"}`} />
            </button>
            {open && (
                <pre className="mt-1 max-h-48 overflow-auto rounded bg-muted/30 p-2 text-xs text-muted-foreground">
                    {str}
                </pre>
            )}
        </div>
    );
}

function CollapsibleJson({
    data,
    label,
}: {
    data: any;
    label: string;
}) {
    const [open, setOpen] = useState(false);
    const str = JSON.stringify(data, null, 2);
    const isLong = str.length > 200;

    return (
        <div className="mt-1">
            {isLong ? (
                <>
                    <button
                        onClick={() => setOpen(!open)}
                        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <ChevronDown
                            className={`h-3 w-3 transition-transform ${open ? "rotate-0" : "-rotate-90"}`}
                        />
                        {label} ({str.length} chars)
                    </button>
                    {open && (
                        <pre className="mt-1 max-h-60 overflow-auto rounded bg-muted/30 p-2 text-xs text-muted-foreground">
                            {str}
                        </pre>
                    )}
                </>
            ) : (
                <pre className="rounded bg-muted/30 p-2 text-xs text-muted-foreground">
                    {str}
                </pre>
            )}
        </div>
    );
}

function TimelineStep({
    step,
    messages,
}: {
    step: number;
    messages: Message[];
}) {
    // Group assistant message with its tool results
    const assistantMsg = messages.find((m) => m.role === "assistant");
    const toolMsgs = messages.filter((m) => m.role === "tool");

    if (!assistantMsg) return null;

    const toolCalls = assistantMsg.tool_calls || [];

    return (
        <div className="relative flex gap-4 pb-6">
            {/* Timeline line */}
            <div className="flex flex-col items-center">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                    {step}
                </div>
                <div className="w-px flex-1 bg-border" />
            </div>

            <div className="flex-1 space-y-3 pt-1">
                {/* Agent thinking/text */}
                {assistantMsg.content && (
                    <div className="rounded-lg border bg-card/50 p-3">
                        <div className="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-emerald-400">
                            <Brain className="h-3 w-3" />
                            Agente piensa
                            {assistantMsg.tokens && (
                                <span className="ml-auto text-muted-foreground">
                                    {assistantMsg.tokens} tokens
                                </span>
                            )}
                        </div>
                        <p className="text-sm leading-relaxed text-foreground">
                            {assistantMsg.content}
                        </p>
                    </div>
                )}

                {/* Tool calls + results */}
                {toolCalls.map((tc: any, i: number) => {
                    const toolName = tc.function?.name || "unknown";
                    let args: Record<string, any> = {};
                    try {
                        args = JSON.parse(tc.function?.arguments || "{}");
                    } catch {}

                    const matchingResult = toolMsgs.find(
                        (m) => m.tool_call_id === tc.id,
                    );
                    const result = matchingResult?.tool_result;
                    const isError = result && typeof result === "object" && "error" in result;
                    const isDone = toolName === "done";
                    const isAction = [
                        "set_stop_loss",
                        "set_take_profit",
                        "cancel_all_orders",
                        "stop_bot",
                        "close_position",
                    ].includes(toolName);
                    const ToolIcon = isDone ? CheckCircle2 : isAction ? Shield : Wrench;

                    return (
                        <div
                            key={i}
                            className={`rounded-lg border p-3 ${
                                isDone
                                    ? "border-primary/40 bg-primary/10"
                                    : isAction
                                      ? "border-yellow-500/30 bg-yellow-500/5"
                                      : "bg-muted/10"
                            }`}
                        >
                            <div className="flex items-center gap-2 flex-wrap">
                                <ToolIcon
                                    className={`h-4 w-4 shrink-0 ${
                                        isDone ? "text-primary" : isAction ? "text-yellow-400" : "text-muted-foreground"
                                    }`}
                                />
                                <span className="font-mono text-sm font-medium">
                                    {toolName}
                                </span>
                                {Object.keys(args).length > 0 && (
                                    <span className="text-xs text-muted-foreground font-normal">
                                        {formatArgsCompact(args)}
                                    </span>
                                )}
                                {isAction && (
                                    <Badge className="ml-auto bg-yellow-500/20 text-yellow-400 text-xs">
                                        ACCIÓN
                                    </Badge>
                                )}
                            </div>

                            {result && !isDone && (
                                <ToolResultDisplay result={result} isError={!!isError} />
                            )}
                            {isDone && (args.analysis || args.summary) && (
                                <div className="mt-3 rounded-lg border border-primary/30 bg-primary/5 p-4">
                                    <p className="text-xs font-medium text-primary mb-1.5">Análisis final</p>
                                    <p className="text-sm leading-relaxed text-foreground">
                                        {args.analysis || args.summary}
                                    </p>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default function ConversationView({
    conversation,
}: {
    conversation: ConversationData;
}) {
    const statusColor =
        conversation.status === "completed"
            ? "text-emerald-400"
            : conversation.status === "running"
              ? "text-blue-400"
              : "text-red-400";

    // Group messages into steps: each assistant message + its tool results
    const steps: Message[][] = [];
    let currentStep: Message[] = [];

    for (const msg of conversation.messages || []) {
        if (msg.role === "system" || msg.role === "user") continue;

        if (msg.role === "assistant") {
            if (currentStep.length > 0) {
                steps.push(currentStep);
            }
            currentStep = [msg];
        } else if (msg.role === "tool") {
            currentStep.push(msg);
        }
    }
    if (currentStep.length > 0) steps.push(currentStep);

    return (
        <AuthenticatedLayout>
            <Head title={`Consulta #${conversation.id}`} />
            <div className="mx-auto max-w-4xl space-y-4 p-4 text-foreground sm:p-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href="/ai-agent">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-lg font-bold">
                                Consulta #{conversation.id}
                            </h1>
                            <Badge variant="secondary">
                                {conversation.bot?.symbol || "?"}
                            </Badge>
                            <Badge
                                variant="outline"
                                className={statusColor}
                            >
                                {conversation.status}
                            </Badge>
                            <Badge variant="outline">
                                {conversation.trigger}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {new Date(conversation.created_at).toLocaleString()}
                            {conversation.model && ` · ${conversation.model}`}
                        </p>
                    </div>
                </div>

                {/* Stats bar */}
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <MessageSquare className="h-3.5 w-3.5" />
                                Tool calls
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">{conversation.total_tool_calls}</p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Brain className="h-3.5 w-3.5" />
                                Tokens
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">{conversation.total_tokens}</p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock className="h-3.5 w-3.5" />
                                Duración
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {conversation.duration_ms
                                    ? `${(conversation.duration_ms / 1000).toFixed(1)}s`
                                    : "—"}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50 hidden sm:block">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <MessageSquare className="h-3.5 w-3.5" />
                                Mensajes
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">{conversation.total_messages}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Analysis & Summary Card */}
                {(conversation.analysis || conversation.summary) && (
                    <Card className="border-primary/20 bg-primary/5">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center gap-1.5 text-xs font-medium text-primary">
                                <Brain className="h-3.5 w-3.5" />
                                Análisis del Agente
                            </div>
                            {conversation.analysis && (
                                <p className="text-sm leading-relaxed text-foreground">
                                    {conversation.analysis}
                                </p>
                            )}
                            {conversation.summary && (
                                <p className="text-xs text-muted-foreground italic">
                                    {conversation.summary}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Actions Executed */}
                {conversation.action_logs &&
                    conversation.action_logs.length > 0 && (
                        <Card className="border-yellow-500/20 bg-yellow-500/5">
                            <CardContent className="p-4">
                                <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-yellow-400">
                                    <Shield className="h-3.5 w-3.5" />
                                    Acciones Ejecutadas sobre el Bot
                                </div>
                                <div className="space-y-2">
                                    {conversation.action_logs.map((a) => (
                                        <div
                                            key={a.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <CheckCircle2 className="h-3.5 w-3.5 text-yellow-400" />
                                            <span className="font-medium">
                                                {actionLabels[a.action] ||
                                                    a.action}
                                            </span>
                                            {a.details && (
                                                <span className="text-xs text-muted-foreground">
                                                    →{" "}
                                                    {Object.entries(a.details)
                                                        .map(
                                                            ([k, v]) =>
                                                                `${k}: ${v}`,
                                                        )
                                                        .join(", ")}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                {/* Timeline */}
                <Card className="bg-card/50">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MessageSquare className="h-4 w-4" />
                            Pasos del Agente
                            <span className="text-xs font-normal text-muted-foreground">
                                {steps.length} pasos ·{" "}
                                {conversation.total_tool_calls} tool calls
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <Separator />
                    <CardContent className="p-4">
                        {steps.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Sin pasos registrados
                            </p>
                        ) : (
                            <div className="space-y-0">
                                {steps.map((stepMsgs, i) => (
                                    <TimelineStep
                                        key={i}
                                        step={i + 1}
                                        messages={stepMsgs}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
