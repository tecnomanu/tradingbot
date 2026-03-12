import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Brain,
    Clock,
    MessageSquare,
    Play,
    Shield,
    Sparkles,
    Wrench,
} from "lucide-react";
import { useMemo, useRef, useState, useEffect } from "react";
import { Toaster, toast } from "sonner";

interface Conversation {
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
    bot?: { id: number; symbol: string; name: string } | null;
}

interface ActionLog {
    id: number;
    bot_id: number;
    conversation_id: number | null;
    action: string;
    source: string;
    details: Record<string, any> | null;
    created_at: string;
    bot?: { id: number; symbol: string } | null;
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
    stats: {
        total_conversations: number;
        total_tool_calls: number;
        total_actions: number;
        avg_duration: number;
    };
    userBots: UserBot[];
}

function timeAgo(date: Date): string {
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) return "hace unos segundos";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours}h`;
    const days = Math.floor(hours / 24);
    return `hace ${days}d`;
}

function formatTimestamp(date: Date): string {
    const hoursAgo = (Date.now() - date.getTime()) / 3600000;
    const time = date.toLocaleTimeString("es", { hour: "2-digit", minute: "2-digit" });
    if (hoursAgo < 24) return time;
    const day = date.toLocaleDateString("es", { day: "2-digit", month: "2-digit" });
    return `${day} ${time}`;
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

function formatActionDetails(action: string, details: Record<string, any> | null): string {
    if (!details) return "";
    switch (action) {
        case "sl_set":
            return `$${Number(details.price).toLocaleString()}${details.previous ? ` (anterior: $${Number(details.previous).toLocaleString()})` : ""}`;
        case "tp_set":
            return `$${Number(details.price).toLocaleString()}${details.previous ? ` (anterior: $${Number(details.previous).toLocaleString()})` : ""}`;
        case "orders_cancelled":
            return `${details.cancelled_count} órdenes canceladas`;
        case "bot_stopped":
            return details.reason || "";
        case "position_closed":
            return `${details.close_side} ${Math.abs(details.size)} | PNL: ${details.unrealized_pnl}`;
        default:
            return JSON.stringify(details);
    }
}

const INCOMPLETE_ANALYSIS = "Agent did not complete analysis";

function formatActionsTaken(actions: string[]): string {
    return actions.map((a) => actionLabels[a] || a).join(", ");
}

function SectionDivider({ label }: { label: string }) {
    return (
        <div className="flex items-center gap-3 px-4 py-2 bg-muted/30">
            <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                {label}
            </span>
            <div className="h-px flex-1 bg-border" />
        </div>
    );
}

export default function AiAgentIndex({
    conversations,
    actionLogs,
    stats,
    userBots,
}: Props) {
    const flash = usePage().props.flash as any;
    const [selectedBot, setSelectedBot] = useState<string>("all");
    const [consulting, setConsulting] = useState(false);

    const filterBotId = selectedBot === "all" ? null : parseInt(selectedBot);

    const filteredConversations = useMemo(
        () =>
            filterBotId
                ? conversations.data.filter((c) => c.bot_id === filterBotId)
                : conversations.data,
        [conversations.data, filterBotId],
    );

    const completedConversations = useMemo(
        () => filteredConversations.filter((c) => c.status === "completed" && c.summary),
        [filteredConversations],
    );

    const filteredActions = useMemo(
        () =>
            filterBotId
                ? actionLogs.filter((a) => a.bot_id === filterBotId)
                : actionLogs,
        [actionLogs, filterBotId],
    );

    const latestAnalysis = completedConversations[0] ?? null;
    const olderAnalyses = completedConversations.slice(1);

    const latestConversation = filteredConversations[0] ?? null;
    const olderConversations = filteredConversations.slice(1);

    const [consultDialogOpen, setConsultDialogOpen] = useState(false);
    const [consultBotId, setConsultBotId] = useState<string>("");

    const openConsultDialog = () => {
        const activeBots = userBots.filter((b) => b.status === "active");
        if (activeBots.length === 1) {
            setConsultBotId(activeBots[0].id.toString());
        } else if (filterBotId) {
            setConsultBotId(filterBotId.toString());
        } else {
            setConsultBotId("");
        }
        setConsultDialogOpen(true);
    };

    const handleConsult = () => {
        const botId = parseInt(consultBotId);
        if (!botId) return;
        setConsultDialogOpen(false);
        setConsulting(true);
        router.post("/ai-agent/consult", { bot_id: botId }, {
            preserveScroll: true,
            onFinish: () => setConsulting(false),
        });
    };

    // Show error toast when returning with flash.error (e.g. consult failed)
    useEffect(() => {
        if (flash?.error) toast.error(flash.error);
    }, [flash?.error]);

    return (
        <AuthenticatedLayout>
            <Toaster theme="system" richColors position="top-right" />
            <Head title="AI Agent" />
            <div className="mx-auto max-w-7xl space-y-6 p-4 text-foreground sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Brain className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">AI Trading Agent</h1>
                            <p className="text-sm text-muted-foreground">
                                Supervisor inteligente con herramientas de trading
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={selectedBot} onValueChange={setSelectedBot}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Filtrar bot" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los bots</SelectItem>
                                {userBots.map((b) => (
                                    <SelectItem key={b.id} value={b.id.toString()}>
                                        #{b.id} {b.symbol}{" "}
                                        <span className="text-muted-foreground">({b.status})</span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            onClick={openConsultDialog}
                            disabled={consulting}
                            className="gap-1.5"
                        >
                            {consulting ? (
                                <Clock className="h-4 w-4 animate-spin" />
                            ) : (
                                <Play className="h-4 w-4" />
                            )}
                            {consulting ? "Consultando..." : "Consultar Agente"}
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
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        { icon: MessageSquare, label: "Consultas", value: stats.total_conversations },
                        { icon: Wrench, label: "Tool Calls", value: stats.total_tool_calls },
                        { icon: Shield, label: "Acciones", value: stats.total_actions },
                        {
                            icon: Clock,
                            label: "Duración Prom.",
                            value: stats.avg_duration ? `${(stats.avg_duration / 1000).toFixed(1)}s` : "—",
                        },
                    ].map(({ icon: Icon, label, value }) => (
                        <Card key={label} className="bg-card/50">
                            <CardContent className="p-3">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Icon className="h-3.5 w-3.5" />
                                    {label}
                                </div>
                                <p className="mt-1 text-xl font-bold tabular-nums">{value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Tabs */}
                <Tabs defaultValue="quick">
                    <TabsList>
                        <TabsTrigger value="quick" className="gap-1.5">
                            <Sparkles className="h-3.5 w-3.5" />
                            Análisis Rápido
                        </TabsTrigger>
                        <TabsTrigger value="actions" className="gap-1.5">
                            <Activity className="h-3.5 w-3.5" />
                            Acciones del Bot
                        </TabsTrigger>
                        <TabsTrigger value="conversations" className="gap-1.5">
                            <MessageSquare className="h-3.5 w-3.5" />
                            Conversaciones
                        </TabsTrigger>
                    </TabsList>

                    {/* Quick Analysis Tab - powered by AiConversation summaries */}
                    <TabsContent value="quick">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {completedConversations.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Sparkles className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">Sin análisis todavía</p>
                                    </div>
                                ) : (
                                    <>
                                        {latestAnalysis && (
                                            <>
                                                <SectionDivider label="Último análisis" />
                                                <QuickAnalysisItem conv={latestAnalysis} highlight />
                                            </>
                                        )}
                                        {olderAnalyses.length > 0 && (
                                            <>
                                                <SectionDivider label="Histórico" />
                                                {olderAnalyses.map((conv) => (
                                                    <QuickAnalysisItem key={conv.id} conv={conv} />
                                                ))}
                                            </>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Actions Tab */}
                    <TabsContent value="actions">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {filteredActions.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Shield className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">Sin acciones registradas</p>
                                        <p className="text-xs text-muted-foreground/80">
                                            Las acciones del agente aparecerán aquí cuando ejecute cambios en tus bots.
                                        </p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Fecha</TableHead>
                                                <TableHead>Tipo</TableHead>
                                                <TableHead>Bot</TableHead>
                                                <TableHead>Detalles</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredActions.map((log) => (
                                                <TableRow key={log.id}>
                                                    <TableCell className="text-xs text-muted-foreground whitespace-nowrap">
                                                        {formatTimestamp(new Date(log.created_at))}
                                                        <br />
                                                        <span className="text-[10px]">{timeAgo(new Date(log.created_at))}</span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className={`font-medium text-sm ${actionColors[log.action] || "text-foreground"}`}>
                                                            {actionLabels[log.action] || log.action}
                                                        </span>
                                                        <Badge variant="outline" className="ml-1.5 text-[10px]">
                                                            {log.source}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="secondary" className="text-[10px]">
                                                            {log.bot?.symbol || `Bot #${log.bot_id}`}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground max-w-[200px] truncate">
                                                        {formatActionDetails(log.action, log.details) || "—"}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Conversations Tab */}
                    <TabsContent value="conversations">
                        <Card className="bg-card/50">
                            <CardContent className="p-0">
                                {filteredConversations.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                                        <Brain className="h-12 w-12 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">
                                            Sin consultas todavía. El agente consulta automáticamente según el intervalo configurado.
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        {latestConversation && (
                                            <>
                                                <SectionDivider label="Última consulta" />
                                                <ConversationItem conv={latestConversation} highlight />
                                            </>
                                        )}
                                        {olderConversations.length > 0 && (
                                            <>
                                                <SectionDivider label="Histórico" />
                                                {olderConversations.map((conv) => (
                                                    <ConversationItem key={conv.id} conv={conv} />
                                                ))}
                                            </>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <Dialog open={consultDialogOpen} onOpenChange={setConsultDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Consultar AI Agent</DialogTitle>
                        <DialogDescription>
                            El agente revisará el estado del bot, analizará el mercado y tomará acciones si es necesario.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-2">
                        <label className="text-sm font-medium">Seleccioná el bot a consultar</label>
                        <Select value={consultBotId} onValueChange={setConsultBotId}>
                            <SelectTrigger>
                                <SelectValue placeholder="Elegí un bot..." />
                            </SelectTrigger>
                            <SelectContent>
                                {userBots.map((b) => (
                                    <SelectItem key={b.id} value={b.id.toString()}>
                                        <span className="flex items-center gap-2">
                                            #{b.id} {b.symbol}
                                            <Badge
                                                variant={b.status === "active" ? "default" : "secondary"}
                                                className="text-[10px]"
                                            >
                                                {b.status}
                                            </Badge>
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConsultDialogOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={handleConsult} disabled={!consultBotId} className="gap-1.5">
                            <Play className="h-4 w-4" />
                            Consultar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}

function QuickAnalysisItem({ conv, highlight }: { conv: Conversation; highlight?: boolean }) {
    const d = new Date(conv.created_at);
    const hasActions = conv.actions_taken && conv.actions_taken.length > 0;
    const isIncomplete = conv.summary === INCOMPLETE_ANALYSIS;
    const [expanded, setExpanded] = useState(false);
    const [clamped, setClamped] = useState(false);
    const textRef = useRef<HTMLParagraphElement>(null);

    useEffect(() => {
        const el = textRef.current;
        if (el && !highlight) {
            setClamped(el.scrollHeight > el.clientHeight + 2);
        }
    }, [conv.summary, highlight]);

    return (
        <Link
            href={`/ai-agent/conversations/${conv.id}`}
            className={`block p-4 space-y-1 transition-colors hover:bg-muted/20 ${highlight ? "border-l-2 border-primary" : ""}`}
        >
            <div className="flex items-center gap-2 flex-wrap">
                <Badge variant="secondary" className="text-xs">{conv.bot?.symbol ?? "?"}</Badge>
                {hasActions ? (
                    <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-orange-500/10 border-orange-500/20 text-orange-400">
                        Acción tomada
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
                        Sin cambios
                    </span>
                )}
                {hasActions && conv.actions_taken && (
                    <span className="text-xs text-muted-foreground">
                        ({formatActionsTaken(conv.actions_taken)})
                    </span>
                )}
                {isIncomplete && (
                    <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-amber-500/10 border-amber-500/20 text-amber-400">
                        <AlertTriangle className="h-3 w-3" />
                        Sin análisis
                    </span>
                )}
                <span className="text-xs text-muted-foreground">{conv.total_tool_calls} tools</span>
                <span className="ml-auto text-xs text-muted-foreground whitespace-nowrap">
                    {formatTimestamp(d)} · {timeAgo(d)}
                </span>
            </div>
            {conv.summary && (
                <div>
                    <p
                        ref={textRef}
                        className={`text-sm text-muted-foreground ${!highlight && !expanded ? "line-clamp-2" : ""}`}
                    >
                        {conv.summary}
                    </p>
                    {!highlight && clamped && (
                        <button
                            onClick={(e) => { e.preventDefault(); setExpanded(!expanded); }}
                            className="text-xs text-primary hover:underline mt-0.5"
                        >
                            {expanded ? "ver menos" : "ver más"}
                        </button>
                    )}
                </div>
            )}
        </Link>
    );
}

function ConversationItem({ conv, highlight }: { conv: Conversation; highlight?: boolean }) {
    const d = new Date(conv.created_at);
    const hasActions = conv.actions_taken && conv.actions_taken.length > 0;
    const isIncomplete = conv.summary === INCOMPLETE_ANALYSIS;
    const displayText = conv.analysis || conv.summary;

    return (
        <Link
            href={`/ai-agent/conversations/${conv.id}`}
            className={`flex items-start gap-4 p-4 transition-colors hover:bg-muted/20 ${highlight ? "border-l-2 border-primary" : ""}`}
        >
            <div className="flex-1 space-y-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-sm">{conv.bot?.symbol || "?"}</span>
                    {hasActions ? (
                        <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-orange-500/10 border-orange-500/20 text-orange-400">
                            Acción tomada
                        </span>
                    ) : (
                        <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
                            Sin cambios
                        </span>
                    )}
                    {hasActions && conv.actions_taken && (
                        <span className="text-xs text-muted-foreground">
                            ({formatActionsTaken(conv.actions_taken)})
                        </span>
                    )}
                    {isIncomplete && (
                        <span className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium bg-amber-500/10 border-amber-500/20 text-amber-400">
                            <AlertTriangle className="h-3 w-3" />
                            Sin análisis
                        </span>
                    )}
                    <Badge
                        variant={
                            conv.status === "completed"
                                ? "default"
                                : conv.status === "running"
                                  ? "secondary"
                                  : "destructive"
                        }
                        className="text-[10px]"
                    >
                        {conv.status}
                    </Badge>
                    <Badge variant="outline" className="text-[10px]">{conv.trigger}</Badge>
                    {conv.model && (
                        <span className="text-[10px] text-muted-foreground">· {conv.model}</span>
                    )}
                </div>
                {displayText && !isIncomplete && (
                    <p className="text-sm text-muted-foreground line-clamp-2">{displayText}</p>
                )}
                {isIncomplete && (
                    <p className="text-sm text-amber-400/90 italic">El agente no completó el análisis</p>
                )}
                <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                    <span>{conv.total_tool_calls} tools</span>
                    <span>{conv.total_tokens} tokens</span>
                    {conv.duration_ms && <span>{(conv.duration_ms / 1000).toFixed(1)}s</span>}
                    {hasActions && (
                        <span className="text-orange-400">
                            {conv.actions_taken!.length} acciones
                        </span>
                    )}
                </div>
            </div>
            <div className="flex flex-col items-end gap-0.5 text-xs text-muted-foreground whitespace-nowrap pt-0.5">
                <span>{formatTimestamp(d)}</span>
                <span className="text-[10px]">{timeAgo(d)}</span>
                <ArrowRight className="mt-1 h-3.5 w-3.5" />
            </div>
        </Link>
    );
}
