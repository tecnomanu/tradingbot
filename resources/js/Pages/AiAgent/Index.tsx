import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Activity,
    ArrowRight,
    Brain,
    Clock,
    MessageSquare,
    Play,
    Shield,
    Sparkles,
    Wrench,
    Zap,
} from "lucide-react";
import { useState } from "react";

interface Conversation {
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
    bot?: { id: number; symbol: string; name: string } | null;
}

interface ActionLog {
    id: number;
    bot_id: number;
    action: string;
    source: string;
    details: Record<string, any> | null;
    created_at: string;
    bot?: { id: number; symbol: string } | null;
}

interface QuickAnalysis {
    id: number;
    symbol: string;
    signal: string | null;
    confidence: number | null;
    reasoning: string | null;
    created_at: string;
}

interface UserBot {
    id: number;
    symbol: string;
    name: string;
    status: string;
}

interface Props {
    conversations: {
        data: Conversation[];
        current_page: number;
        last_page: number;
    };
    actionLogs: ActionLog[];
    quickAnalyses: QuickAnalysis[];
    stats: {
        total_conversations: number;
        total_tool_calls: number;
        total_actions: number;
        avg_duration: number;
        total_quick_analyses: number;
    };
    userBots: UserBot[];
}

function timeAgo(date: Date): string {
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) return "hace unos segundos";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60)
        return `hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24)
        return `hace ${hours}h`;
    const days = Math.floor(hours / 24);
    return `hace ${days}d`;
}

const actionLabels: Record<string, string> = {
    sl_set: "Stop-Loss",
    tp_set: "Take-Profit",
    bot_stopped: "Bot Detenido",
    orders_cancelled: "Órdenes Canceladas",
    position_closed: "Posición Cerrada",
    grid_adjusted: "Grid Ajustado",
};

const actionColors: Record<string, string> = {
    sl_set: "text-yellow-400",
    tp_set: "text-emerald-400",
    bot_stopped: "text-red-400",
    orders_cancelled: "text-orange-400",
    position_closed: "text-red-400",
    grid_adjusted: "text-blue-400",
};

const signalColors: Record<string, string> = {
    bullish: "text-emerald-400",
    bearish: "text-red-400",
    neutral: "text-yellow-400",
};

export default function AiAgentIndex({
    conversations,
    actionLogs,
    quickAnalyses,
    stats,
    userBots,
}: Props) {
    const flash = usePage().props.flash as any;
    const [selectedBot, setSelectedBot] = useState<string>(
        userBots[0]?.id?.toString() || "",
    );
    const [consulting, setConsulting] = useState(false);

    const handleConsult = () => {
        if (!selectedBot) return;
        setConsulting(true);
        router.post(
            "/ai-agent/consult",
            { bot_id: parseInt(selectedBot) },
            { preserveScroll: true, onFinish: () => setConsulting(false) },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="AI Agent" />
            <div className="mx-auto max-w-7xl space-y-6 p-4 text-foreground sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Brain className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                AI Trading Agent
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Supervisor inteligente con herramientas de
                                trading
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Select
                            value={selectedBot}
                            onValueChange={setSelectedBot}
                        >
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Seleccionar bot" />
                            </SelectTrigger>
                            <SelectContent>
                                {userBots.map((b) => (
                                    <SelectItem
                                        key={b.id}
                                        value={b.id.toString()}
                                    >
                                        #{b.id} {b.symbol}{" "}
                                        <span className="text-muted-foreground">
                                            ({b.status})
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            onClick={handleConsult}
                            disabled={!selectedBot || consulting}
                            className="gap-1.5"
                        >
                            {consulting ? (
                                <Clock className="h-4 w-4 animate-spin" />
                            ) : (
                                <Play className="h-4 w-4" />
                            )}
                            {consulting
                                ? "Consultando..."
                                : "Consultar Agente"}
                        </Button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-emerald-400">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-400">
                        {flash.error}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <MessageSquare className="h-3.5 w-3.5" />
                                Consultas
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {stats.total_conversations}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Wrench className="h-3.5 w-3.5" />
                                Tool Calls
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {stats.total_tool_calls}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Shield className="h-3.5 w-3.5" />
                                Acciones
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {stats.total_actions}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock className="h-3.5 w-3.5" />
                                Duración Prom.
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {stats.avg_duration
                                    ? `${(stats.avg_duration / 1000).toFixed(1)}s`
                                    : "—"}
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/50">
                        <CardContent className="p-3">
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Zap className="h-3.5 w-3.5" />
                                Análisis Rápidos
                            </div>
                            <p className="mt-1 text-xl font-bold tabular-nums">
                                {stats.total_quick_analyses}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="conversations">
                    <TabsList>
                        <TabsTrigger value="conversations" className="gap-1.5">
                            <MessageSquare className="h-3.5 w-3.5" />
                            Conversaciones
                        </TabsTrigger>
                        <TabsTrigger value="actions" className="gap-1.5">
                            <Activity className="h-3.5 w-3.5" />
                            Acciones del Bot
                        </TabsTrigger>
                        <TabsTrigger value="quick" className="gap-1.5">
                            <Sparkles className="h-3.5 w-3.5" />
                            Análisis Rápido
                        </TabsTrigger>
                    </TabsList>

                    {/* Conversations Tab */}
                    <TabsContent value="conversations">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {conversations.data.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Brain className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">
                                            Sin consultas todavía. El agente
                                            consulta automáticamente cada 15
                                            min.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {conversations.data.map(
                                            (conv, idx) => {
                                                const seqNum =
                                                    conversations.data.length -
                                                    idx;
                                                const d = new Date(
                                                    conv.created_at,
                                                );
                                                const ago = timeAgo(d);
                                                const hasActions =
                                                    conv.actions_taken &&
                                                    conv.actions_taken.length >
                                                        0;
                                                return (
                                                    <Link
                                                        key={conv.id}
                                                        href={`/ai-agent/conversations/${conv.id}`}
                                                        className="flex items-start gap-4 p-4 transition-colors hover:bg-muted/20"
                                                    >
                                                        <div className="flex flex-col items-center gap-1 pt-0.5">
                                                            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                                                {seqNum}
                                                            </span>
                                                            {hasActions && (
                                                                <span className="h-1.5 w-1.5 rounded-full bg-yellow-400" />
                                                            )}
                                                        </div>
                                                        <div className="flex-1 space-y-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {conv.bot
                                                                        ?.symbol ||
                                                                        "?"}
                                                                </span>
                                                                <Badge
                                                                    variant={
                                                                        conv.status ===
                                                                        "completed"
                                                                            ? "default"
                                                                            : conv.status ===
                                                                                "running"
                                                                              ? "secondary"
                                                                              : "destructive"
                                                                    }
                                                                    className="text-[10px]"
                                                                >
                                                                    {
                                                                        conv.status
                                                                    }
                                                                </Badge>
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-[10px]"
                                                                >
                                                                    {
                                                                        conv.trigger
                                                                    }
                                                                </Badge>
                                                                {conv.model && (
                                                                    <span className="text-[10px] text-muted-foreground">
                                                                        ·{" "}
                                                                        {
                                                                            conv.model
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {conv.summary && (
                                                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                                                    {
                                                                        conv.summary
                                                                    }
                                                                </p>
                                                            )}
                                                            <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                                                <span>
                                                                    {
                                                                        conv.total_tool_calls
                                                                    }{" "}
                                                                    tools
                                                                </span>
                                                                <span>
                                                                    {
                                                                        conv.total_tokens
                                                                    }{" "}
                                                                    tokens
                                                                </span>
                                                                {conv.duration_ms && (
                                                                    <span>
                                                                        {(
                                                                            conv.duration_ms /
                                                                            1000
                                                                        ).toFixed(
                                                                            1,
                                                                        )}
                                                                        s
                                                                    </span>
                                                                )}
                                                                {hasActions && (
                                                                    <span className="text-yellow-400">
                                                                        {
                                                                            conv
                                                                                .actions_taken!
                                                                                .length
                                                                        }{" "}
                                                                        acciones
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="flex flex-col items-end gap-0.5 text-xs text-muted-foreground whitespace-nowrap pt-0.5">
                                                            <span>
                                                                {d.toLocaleDateString(
                                                                    "es",
                                                                    {
                                                                        day: "2-digit",
                                                                        month: "2-digit",
                                                                    },
                                                                )}{" "}
                                                                {d.toLocaleTimeString(
                                                                    "es",
                                                                    {
                                                                        hour: "2-digit",
                                                                        minute: "2-digit",
                                                                    },
                                                                )}
                                                            </span>
                                                            <span className="text-[10px]">
                                                                {ago}
                                                            </span>
                                                            <ArrowRight className="mt-1 h-3.5 w-3.5" />
                                                        </div>
                                                    </Link>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Actions Tab */}
                    <TabsContent value="actions">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {actionLogs.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Shield className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">
                                            Sin acciones registradas
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {actionLogs.map((log) => (
                                            <div
                                                key={log.id}
                                                className="flex items-start justify-between gap-4 p-4"
                                            >
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`font-medium ${actionColors[log.action] || "text-foreground"}`}
                                                        >
                                                            {actionLabels[
                                                                log.action
                                                            ] || log.action}
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {log.source}
                                                        </Badge>
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-xs"
                                                        >
                                                            {log.bot?.symbol ||
                                                                `Bot #${log.bot_id}`}
                                                        </Badge>
                                                    </div>
                                                    {log.details && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {JSON.stringify(
                                                                log.details,
                                                            )}
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="whitespace-nowrap text-xs text-muted-foreground">
                                                    {new Date(
                                                        log.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Quick Analysis Tab */}
                    <TabsContent value="quick">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {quickAnalyses.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Sparkles className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">
                                            Sin análisis rápidos
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {quickAnalyses.map((a) => (
                                            <div
                                                key={a.id}
                                                className="p-4 space-y-1"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`font-medium ${signalColors[a.signal || "neutral"]}`}
                                                    >
                                                        {a.signal || "—"}
                                                    </span>
                                                    <Badge variant="secondary">
                                                        {a.symbol}
                                                    </Badge>
                                                    {a.confidence != null && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {Math.round(
                                                                a.confidence *
                                                                    100,
                                                            )}
                                                            % confianza
                                                        </span>
                                                    )}
                                                    <span className="ml-auto text-xs text-muted-foreground">
                                                        {new Date(
                                                            a.created_at,
                                                        ).toLocaleString()}
                                                    </span>
                                                </div>
                                                {a.reasoning && (
                                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                                        {a.reasoning}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
