import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    Bot,
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

const actionLabels: Record<string, string> = {
    sl_set: "Stop-Loss configurado",
    tp_set: "Take-Profit configurado",
    bot_stopped: "Bot detenido",
    orders_cancelled: "Órdenes canceladas",
    position_closed: "Posición cerrada",
};

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
                    let args: any = {};
                    try {
                        args = JSON.parse(tc.function?.arguments || "{}");
                    } catch {}

                    const matchingResult = toolMsgs.find(
                        (m) => m.tool_call_id === tc.id,
                    );
                    const result = matchingResult?.tool_result;
                    const isError = result && "error" in result;
                    const isDone = toolName === "done";
                    const isAction = [
                        "set_stop_loss",
                        "set_take_profit",
                        "cancel_all_orders",
                        "stop_bot",
                        "close_position",
                    ].includes(toolName);

                    return (
                        <div
                            key={i}
                            className={`rounded-lg border p-3 ${
                                isDone
                                    ? "border-primary/30 bg-primary/5"
                                    : isAction
                                      ? "border-yellow-500/30 bg-yellow-500/5"
                                      : "bg-muted/10"
                            }`}
                        >
                            <div className="flex items-center gap-2">
                                {isDone ? (
                                    <CheckCircle2 className="h-4 w-4 text-primary" />
                                ) : isAction ? (
                                    <Shield className="h-4 w-4 text-yellow-400" />
                                ) : (
                                    <Wrench className="h-4 w-4 text-muted-foreground" />
                                )}
                                <span className="font-mono text-sm font-medium">
                                    {toolName}
                                </span>
                                {Object.keys(args).length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        ({JSON.stringify(args)})
                                    </span>
                                )}
                                {isAction && (
                                    <Badge className="ml-auto bg-yellow-500/20 text-yellow-400 text-xs">
                                        ACCIÓN
                                    </Badge>
                                )}
                            </div>

                            {result && (
                                <div className="mt-2">
                                    {isDone && result.summary ? (
                                        <p className="text-sm text-foreground">
                                            {result.summary}
                                        </p>
                                    ) : (
                                        <CollapsibleJson
                                            data={result}
                                            label="Resultado"
                                        />
                                    )}
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
                            {new Date(
                                conversation.created_at,
                            ).toLocaleString()}{" "}
                            ·{" "}
                            {conversation.duration_ms
                                ? `${(conversation.duration_ms / 1000).toFixed(1)}s`
                                : "—"}{" "}
                            · {conversation.total_tokens} tokens ·{" "}
                            {conversation.total_tool_calls} tools ·{" "}
                            {conversation.model}
                        </p>
                    </div>
                </div>

                {/* Summary Card */}
                {conversation.summary && (
                    <Card className="border-primary/20 bg-primary/5">
                        <CardContent className="p-4">
                            <div className="mb-2 flex items-center gap-1.5 text-xs font-medium text-primary">
                                <Brain className="h-3.5 w-3.5" />
                                Conclusión del Agente
                            </div>
                            <p className="text-sm leading-relaxed text-foreground">
                                {conversation.summary}
                            </p>
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
