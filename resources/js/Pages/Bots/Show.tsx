import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    AgentImpact,
    Bot,
    BotPnlSnapshot,
    DrawdownMetrics,
    GridConfig,
    Order,
    OrderStats,
    RecentFill,
    RiskGuardStatus,
} from "@/types/bot";
import {
    leverageLabel,
    marginTypeBadgeClass,
    marginTypeLabel,
    modeBadgeClass,
    modeLabel,
    sideBadgeClass,
    sideLabel,
} from "@/utils/botBadges";
import {
    formatCurrency,
    formatDate,
    formatPercent,
    statusLabel,
} from "@/utils/formatters";
import { timeSince } from "@/utils/timeago";
import { RECENT_ACTIVITY_THRESHOLD_MS } from "@/utils/constants";
import AiPromptConfig from "./components/AiPromptConfig";
import BotActivityLog, { type ActivityLogEntry } from "./components/BotActivityLog";
import AgentImpactPanel from "./components/AgentImpactPanel";
import BotHealthPanel, { type BotHealth } from "./components/BotHealthPanel";
import { Head, Link, router } from "@inertiajs/react";
import {
    Tooltip as UITooltip,
    TooltipContent as UITooltipContent,
    TooltipProvider as UITooltipProvider,
    TooltipTrigger as UITooltipTrigger,
} from "@/components/ui/tooltip";
import {
    Activity,
    ArrowLeft,
    Bot as BotIcon,
    Brain,
    CheckCircle,
    Clock,
    FlaskConical,
    History,
    Loader2,
    Monitor,
    Pencil,
    Play,
    RefreshCw,
    RotateCcw,
    Save,
    Server,
    Shield,
    Trash2,
    User,
    XCircle,
    Zap,
} from "lucide-react";
import { useState } from "react";
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { type BotPreviewData } from "./components/BotPreviewPanel";
import OrdersTable from "./components/OrdersTable";
import TradingViewChart, { ChartOrder } from "./components/TradingViewChart";

interface BinancePosition {
    symbol: string;
    positionAmt: number;
    entryPrice: number;
    unrealizedProfit: number;
    liquidationPrice: number;
    positionSide: string;
    leverage: number | null;
    marginType: string | null;
}

interface ActivityInfo {
    last_order_at: string | null;
    active_orders: number;
    filled_24h: number;
    rounds_24h: number;
}

interface ShowProps {
    bot: Bot;
    orderStats: OrderStats;
    gridConfig: GridConfig;
    orders: { data: Order[]; current_page: number; last_page: number };
    pnlHistory: BotPnlSnapshot[];
    drawdown: DrawdownMetrics;
    riskGuard: RiskGuardStatus;
    recentFills?: RecentFill[];
    position?: BinancePosition | null;
    activity: ActivityInfo;
    chartOrders?: ChartOrder[];
    activityLogs?: ActivityLogEntry[];
    health?: BotHealth;
    agentImpact?: AgentImpact;
}

export default function Show({
    bot,
    orderStats,
    gridConfig,
    orders,
    pnlHistory,
    drawdown,
    riskGuard,
    recentFills = [],
    position,
    activity,
    chartOrders = [],
    activityLogs = [],
    health,
    agentImpact,
}: ShowProps) {
    const isRunning = bot.status === "active";
    const roiPct = bot.real_investment
        ? (Number(bot.total_pnl) / Number(bot.real_investment)) * 100
        : 0;
    const hasRecentActivity =
        activity.last_order_at &&
        Date.now() - new Date(activity.last_order_at).getTime() < RECENT_ACTIVITY_THRESHOLD_MS;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/bots">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-bold tracking-tight">
                                    {bot.symbol.replace("USDT", "/USDT")} Grid Bot
                                </h1>
                                <Badge
                                    variant={
                                        bot.status === "active"
                                            ? "default"
                                            : bot.status === "error"
                                              ? "destructive"
                                              : "secondary"
                                    }
                                >
                                    {statusLabel(bot.status)}
                                </Badge>
                                {bot.stop_reason === "risk_guard" && bot.status === "stopped" && (
                                    <Badge variant="outline" className="text-[10px] border-orange-500 text-orange-500">Risk Guard</Badge>
                                )}
                                {bot.risk_guard_level === "soft" && bot.status === "active" && (
                                    <Badge className="text-[10px] bg-amber-500/20 text-amber-500 border-amber-500/30">Protegido</Badge>
                                )}
                                <span className={modeBadgeClass(Number(bot.leverage) > 1, "sm")}>
                                    {modeLabel(Number(bot.leverage) > 1)}
                                </span>
                                <span className={sideBadgeClass(bot.side, "sm")}>
                                    {sideLabel(bot.side)}
                                </span>
                                {Number(bot.leverage) > 1 && (
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {leverageLabel(bot.leverage)}
                                    </span>
                                )}
                                {bot.margin_type && (
                                    <span className={marginTypeBadgeClass(bot.margin_type, "sm")}>
                                        {marginTypeLabel(bot.margin_type)}
                                    </span>
                                )}
                                <UITooltipProvider delayDuration={150}>
                                    <UITooltip>
                                        <UITooltipTrigger asChild>
                                            <span className="inline-flex cursor-help">
                                                <Brain className={`h-4 w-4 ${bot.ai_agent_enabled ? "text-emerald-400" : "text-muted-foreground/35"}`} />
                                            </span>
                                        </UITooltipTrigger>
                                        <UITooltipContent side="bottom" className="text-xs">
                                            {bot.ai_agent_enabled
                                                ? `Agente AI activo — consulta cada ${bot.ai_consultation_interval} min`
                                                : "Agente AI desactivado"}
                                        </UITooltipContent>
                                    </UITooltip>
                                </UITooltipProvider>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {bot.name} · Creado {formatDate(bot.created_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                        >
                            <Link href={`/bots/${bot.id}/edit`}>
                                <Pencil className="mr-1 h-3 w-3" /> Editar
                            </Link>
                        </Button>
                        {bot.status === "active" && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post("/ai-agent/consult", {
                                        bot_id: bot.id,
                                    })
                                }
                            >
                                <Brain className="mr-1 h-3 w-3" /> Consultar
                                Agente
                            </Button>
                        )}
                        {bot.status === "active" ? (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        Detener
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            ¿Detener bot{" "}
                                            {bot.symbol.replace(
                                                "USDT",
                                                "/USDT",
                                            )}
                                            ?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Se cancelarán todas las órdenes
                                            abiertas y el bot dejará de operar.
                                            Esta acción no cierra posiciones
                                            abiertas.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancelar
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                router.post(
                                                    `/bots/${bot.id}/stop`,
                                                )
                                            }
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        >
                                            Detener bot
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        ) : (
                            (bot.status === "pending" ||
                                bot.status === "stopped") && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button size="sm">
                                            <Play className="mr-1 h-3 w-3" /> Iniciar
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                ¿Iniciar bot{" "}
                                                {bot.symbol.replace("USDT", "/USDT")}?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                El bot comenzará a operar y colocará órdenes en Binance.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() =>
                                                    router.post(`/bots/${bot.id}/start`)
                                                }
                                            >
                                                Iniciar bot
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )
                        )}
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        ¿Eliminar bot {bot.symbol.replace("USDT", "/USDT")}?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Esta acción es irreversible. Se eliminará el bot y todo su historial de órdenes.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => router.delete(`/bots/${bot.id}`)}
                                        className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                    >
                                        Eliminar
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>
            }
        >
            <Head title={`Bot - ${bot.name}`} />

            {/* Activity Banner */}
            <div className="mb-4 flex items-center justify-between gap-3 rounded-lg border px-4 py-2.5 bg-card/50">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2">
                        {isRunning ? (
                            hasRecentActivity ? (
                                <span className="relative flex h-2.5 w-2.5">
                                    <span className="absolute inline-flex h-full w-full motion-safe:animate-ping rounded-full bg-green-400 opacity-75" />
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500" />
                                </span>
                            ) : (
                                <Activity className="h-3.5 w-3.5 text-yellow-500" />
                            )
                        ) : (
                            <XCircle className="h-3.5 w-3.5 text-muted-foreground" />
                        )}
                        <span className="text-xs font-medium">
                            {isRunning
                                ? hasRecentActivity
                                    ? "Operando activamente"
                                    : "Bot activo, esperando movimiento"
                                : "Bot detenido"}
                        </span>
                    </div>
                    <span className="text-muted-foreground">·</span>
                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Clock className="h-3 w-3" />
                        Última orden: {timeSince(activity.last_order_at)}
                    </div>
                    <span className="text-muted-foreground">·</span>
                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                        <CheckCircle className="h-3 w-3" />
                        {activity.active_orders} abiertas · {activity.filled_24h}{" "}
                        ejecutadas 24h · {activity.rounds_24h} rondas 24h
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="shrink-0"
                    onClick={() => router.reload()}
                >
                    <RefreshCw className="mr-1 h-3.5 w-3.5" />
                    Actualizar
                </Button>
            </div>

            {/* Stats Banner */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-6">
                {[
                    {
                        label: "Inversión real",
                        value: `${formatCurrency(bot.real_investment)} USDT`,
                    },
                    {
                        label: "PNL Neto Total",
                        value: `${bot.total_pnl >= 0 ? "+" : ""}${formatCurrency(bot.total_pnl)} USDT`,
                        color:
                            bot.total_pnl >= 0
                                ? "text-green-500"
                                : "text-destructive",
                    },
                    {
                        label: "Grid Neto",
                        value: `${formatCurrency(bot.grid_profit)} USDT`,
                        color: "text-primary",
                    },
                    {
                        label: "Comisiones",
                        value: `-${formatCurrency(bot.total_fees)} USDT`,
                        color: "text-orange-500",
                    },
                    {
                        label: "Rondas totales",
                        value: String(bot.total_rounds),
                    },
                    { label: "Rondas 24h", value: String(bot.rounds_24h) },
                ].map((stat, i) => (
                    <Card key={i}>
                        <CardContent className="pt-4 pb-3 text-center">
                            <p className="text-xs text-muted-foreground">
                                {stat.label}
                            </p>
                            <p
                                className={`mt-1 text-sm font-bold ${stat.color || ""}`}
                            >
                                {stat.value}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Tabs */}
            <Tabs defaultValue="envivo" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="envivo" className="gap-1.5">
                        <span className="relative flex h-2 w-2">
                            {isRunning && (
                                <span className="absolute inline-flex h-full w-full motion-safe:animate-ping rounded-full bg-green-400 opacity-75" />
                            )}
                            <span className={`relative inline-flex h-2 w-2 rounded-full ${isRunning ? "bg-green-500" : "bg-muted-foreground"}`} />
                        </span>
                        En Vivo
                    </TabsTrigger>
                    <TabsTrigger value="resumen">Resumen</TabsTrigger>
                    <TabsTrigger value="ordenes">
                        Órdenes ({orderStats.total_orders})
                    </TabsTrigger>
                    <TabsTrigger value="parametros">Parámetros</TabsTrigger>
                    <TabsTrigger value="pnl">PNL</TabsTrigger>
                    <TabsTrigger value="ai" className="gap-1.5">
                        <Brain className="h-3.5 w-3.5" />
                        Agente IA
                    </TabsTrigger>
                    <TabsTrigger value="historial" className="gap-1.5">
                        <History className="h-3.5 w-3.5" />
                        Historial
                        {activityLogs.length > 0 && (
                            <Badge variant="secondary" className="ml-1 text-[10px]">
                                {activityLogs.length}
                            </Badge>
                        )}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="envivo" className="space-y-4">
                    <Card>
                        <CardContent className="p-0">
                            <div className="h-[500px]">
                                <TradingViewChart
                                    symbol={bot.symbol}
                                    lowerPrice={Number(bot.price_lower)}
                                    upperPrice={Number(bot.price_upper)}
                                    gridCount={Number(bot.grid_count)}
                                    side={bot.side}
                                    orders={chartOrders}
                                    botPreview={{
                                        symbol: bot.symbol,
                                        side: bot.side,
                                        priceLower: Number(bot.price_lower),
                                        priceUpper: Number(bot.price_upper),
                                        gridCount: Number(bot.grid_count),
                                        investment: String(bot.investment),
                                        leverage: String(bot.leverage),
                                        slippage: String(bot.slippage),
                                        stopLoss: bot.stop_loss_price ? String(bot.stop_loss_price) : undefined,
                                        takeProfit: bot.take_profit_price ? String(bot.take_profit_price) : undefined,
                                        gridMode: bot.grid_mode || "arithmetic",
                                        botMode: Number(bot.leverage) > 1 ? "futures" : "spot",
                                        status: bot.status,
                                        totalPnl: bot.total_pnl,
                                        gridProfit: bot.grid_profit,
                                        pendingOrders: chartOrders.filter(o => o.status === "open").length,
                                        filledOrders: chartOrders.filter(o => o.status === "filled").length,
                                    } satisfies BotPreviewData}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {/* Active Orders summary */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm flex items-center gap-2">
                                    Órdenes Pendientes
                                    <Badge variant="secondary" className="text-[10px]">
                                        {chartOrders.filter(o => o.status === "open").length}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="max-h-56 overflow-y-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b text-muted-foreground">
                                                <th className="pb-1.5 text-left font-medium">Precio</th>
                                                <th className="pb-1.5 text-left font-medium">Lado</th>
                                                <th className="pb-1.5 text-right font-medium">Cantidad</th>
                                                <th className="pb-1.5 text-right font-medium">Creada</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {chartOrders
                                                .filter(o => o.status === "open")
                                                .sort((a, b) => b.price - a.price)
                                                .map(o => (
                                                    <tr key={o.id} className="hover:bg-accent/50">
                                                        <td className="py-1.5 tabular-nums font-mono">
                                                            {formatCurrency(o.price, 2)}
                                                        </td>
                                                        <td className="py-1.5">
                                                            <span className={o.side === "buy" ? "text-green-500" : "text-red-500"}>
                                                                {o.side === "buy" ? "Compra" : "Venta"}
                                                            </span>
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {o.quantity.toFixed(5)}
                                                        </td>
                                                        <td className="py-1.5 text-right text-muted-foreground">
                                                            {o.created_at_fmt ?? "—"}
                                                        </td>
                                                    </tr>
                                                ))}
                                            {chartOrders.filter(o => o.status === "open").length === 0 && (
                                                <tr>
                                                    <td colSpan={4} className="py-4 text-center text-muted-foreground">
                                                        Sin órdenes pendientes
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Recently filled */}
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm flex items-center gap-2">
                                    Últimas Ejecuciones
                                    <Badge variant="secondary" className="text-[10px]">
                                        {chartOrders.filter(o => o.status === "filled").length}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="max-h-56 overflow-y-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b text-muted-foreground">
                                                <th className="pb-1.5 text-left font-medium">Precio</th>
                                                <th className="pb-1.5 text-left font-medium">Lado</th>
                                                <th className="pb-1.5 text-right font-medium">Cantidad</th>
                                                <th className="pb-1.5 text-right font-medium">Ejecutada</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {chartOrders
                                                .filter(o => o.status === "filled")
                                                .sort((a, b) => b.time - a.time)
                                                .slice(0, 20)
                                                .map(o => (
                                                    <tr key={o.id} className="hover:bg-accent/50">
                                                        <td className="py-1.5 tabular-nums font-mono">
                                                            {formatCurrency(o.price, 2)}
                                                        </td>
                                                        <td className="py-1.5">
                                                            <span className={o.side === "buy" ? "text-green-500" : "text-red-500"}>
                                                                {o.side === "buy" ? "Compra" : "Venta"}
                                                            </span>
                                                        </td>
                                                        <td className="py-1.5 text-right tabular-nums">
                                                            {o.quantity.toFixed(5)}
                                                        </td>
                                                        <td className="py-1.5 text-right text-muted-foreground">
                                                            {o.filled_at_fmt ?? "—"}
                                                        </td>
                                                    </tr>
                                                ))}
                                            {chartOrders.filter(o => o.status === "filled").length === 0 && (
                                                <tr>
                                                    <td colSpan={4} className="py-4 text-center text-muted-foreground">
                                                        Sin ejecuciones aún
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Position info inline */}
                    {position && position.positionAmt !== 0 && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">
                                    Posición Abierta
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-6 text-xs">
                                    {[
                                        { label: "Tamaño", value: `${position.positionAmt} ${bot.symbol.replace("USDT", "")}` },
                                        { label: "Entrada", value: `${formatCurrency(position.entryPrice, 2)} USDT` },
                                        {
                                            label: "PNL no realizado",
                                            value: `${position.unrealizedProfit >= 0 ? "+" : ""}${formatCurrency(position.unrealizedProfit)} USDT`,
                                            color: position.unrealizedProfit >= 0 ? "text-green-500" : "text-destructive",
                                        },
                                        {
                                            label: "Liq. price",
                                            value: position.liquidationPrice > 0 ? `${formatCurrency(position.liquidationPrice, 1)}` : "N/A",
                                        },
                                        {
                                            label: "Lado",
                                            value: position.positionAmt > 0 ? "LONG" : "SHORT",
                                            color: position.positionAmt > 0 ? "text-green-500" : "text-destructive",
                                        },
                                        {
                                            label: "Margen",
                                            value: marginTypeLabel(position.marginType ?? bot.margin_type),
                                        },
                                    ].map((item, i) => (
                                        <div key={i} className="space-y-0.5">
                                            <p className="text-muted-foreground">{item.label}</p>
                                            <p className={`font-semibold tabular-nums ${(item as any).color || ""}`}>
                                                {item.value}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>

                <TabsContent value="resumen" className="space-y-4">
                    {/* Distribution bar like Pionex */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Distribución
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-5 text-xs">
                                {[
                                    {
                                        label: "Inversión real",
                                        value: formatCurrency(
                                            bot.real_investment,
                                        ),
                                    },
                                    {
                                        label: "Margen adic.",
                                        value: formatCurrency(
                                            bot.additional_margin,
                                        ),
                                    },
                                    {
                                        label: "Precio liq.",
                                        value:
                                            bot.est_liquidation_price > 0
                                                ? formatCurrency(
                                                      bot.est_liquidation_price,
                                                  )
                                                : "N/A",
                                    },
                                    {
                                        label: "Rango",
                                        value: `${formatCurrency(bot.price_lower)} - ${formatCurrency(bot.price_upper)}`,
                                    },
                                    {
                                        label: "Rejillas",
                                        value: `${bot.grid_count} (${bot.profit_per_grid}%/rejilla)`,
                                    },
                                ].map((item, i) => (
                                    <div key={i} className="space-y-1">
                                        <p className="text-muted-foreground">
                                            {item.label}
                                        </p>
                                        <p className="font-semibold tabular-nums text-foreground">
                                            {item.value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {health && (
                        <BotHealthPanel health={health} isActive={isRunning} />
                    )}

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {/* Grid Levels */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    Rejillas del Grid
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="max-h-80 overflow-y-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="pb-2 text-left text-xs font-medium text-muted-foreground">
                                                    #
                                                </th>
                                                <th className="pb-2 text-left text-xs font-medium text-muted-foreground">
                                                    Precio
                                                </th>
                                                <th className="pb-2 text-left text-xs font-medium text-muted-foreground">
                                                    Lado
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {Object.entries(
                                                gridConfig.grid_levels,
                                            )
                                                .slice(0, 50)
                                                .map(([level, price]) => {
                                                    const mid =
                                                        Object.keys(
                                                            gridConfig.grid_levels,
                                                        ).length / 2;
                                                    const isBuy =
                                                        parseInt(level) < mid;
                                                    return (
                                                        <tr
                                                            key={level}
                                                            className="hover:bg-accent"
                                                        >
                                                            <td className="py-2 font-mono text-xs">
                                                                {level}
                                                            </td>
                                                            <td className="py-2 font-mono">
                                                                {formatCurrency(
                                                                    price as number,
                                                                    2,
                                                                )}
                                                            </td>
                                                            <td className="py-2">
                                                                <Badge
                                                                    variant={
                                                                        isBuy
                                                                            ? "default"
                                                                            : "destructive"
                                                                    }
                                                                    className="text-[10px]"
                                                                >
                                                                    {isBuy
                                                                        ? "Compra"
                                                                        : "Venta"}
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        {/* PNL Chart */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    Ganancia en USDT
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {pnlHistory.length > 0 ? (
                                    <div className="h-64">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <AreaChart data={pnlHistory}>
                                                <defs>
                                                    <linearGradient
                                                        id="showPnl"
                                                        x1="0"
                                                        y1="0"
                                                        x2="0"
                                                        y2="1"
                                                    >
                                                        <stop
                                                            offset="5%"
                                                            stopColor="#22a962"
                                                            stopOpacity={0.3}
                                                        />
                                                        <stop
                                                            offset="95%"
                                                            stopColor="#22a962"
                                                            stopOpacity={0}
                                                        />
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid
                                                    strokeDasharray="3 3"
                                                    stroke="hsl(var(--border))"
                                                />
                                                <XAxis
                                                    dataKey="time"
                                                    tick={{
                                                        fill: "hsl(var(--muted-foreground))",
                                                        fontSize: 10,
                                                    }}
                                                    interval="preserveStartEnd"
                                                    minTickGap={40}
                                                />
                                                <YAxis
                                                    tick={{
                                                        fill: "hsl(var(--muted-foreground))",
                                                        fontSize: 10,
                                                    }}
                                                    tickFormatter={(v) => `${v >= 0 ? "+" : ""}${formatCurrency(v)}`}
                                                />
                                                <Tooltip
                                                    contentStyle={{
                                                        backgroundColor:
                                                            "hsl(var(--card))",
                                                        border: "1px solid hsl(var(--border))",
                                                        borderRadius: "8px",
                                                        fontSize: "12px",
                                                    }}
                                                    formatter={(value: number | undefined) => [`${(value ?? 0) >= 0 ? "+" : ""}${formatCurrency(value ?? 0)} USDT`, "PNL"]}
                                                    labelFormatter={(label) => `Fecha: ${label}`}
                                                />
                                                <Area
                                                    type="monotone"
                                                    dataKey="total_pnl"
                                                    stroke="#22a962"
                                                    fill="url(#showPnl)"
                                                    strokeWidth={2}
                                                />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <div className="flex h-64 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                                        <p>Sin datos de PNL aún</p>
                                        <p className="text-xs">
                                            Los datos aparecerán cuando el bot esté activo y se registren snapshots
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="ordenes">
                    <Card>
                        <CardContent className="pt-6">
                            <OrdersTable orders={orders} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="parametros">
                    <Card className="max-w-lg">
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Configuración del Bot
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {[
                                ["Par", bot.symbol],
                                ["Dirección", sideLabel(bot.side)],
                                [
                                    "Rango de precios",
                                    `${formatCurrency(bot.price_lower)} ~ ${formatCurrency(bot.price_upper)}`,
                                ],
                                ["Rejillas", String(bot.grid_count)],
                                ["Apalancamiento", `${bot.leverage}x`],
                                ["Tipo de margen", marginTypeLabel(bot.margin_type)],
                                [
                                    "Inversión total",
                                    `${formatCurrency(bot.investment)} USDT`,
                                ],
                                [
                                    "Inversión real",
                                    `${formatCurrency(bot.real_investment)} USDT`,
                                ],
                                [
                                    "Margen adicional",
                                    `${formatCurrency(bot.additional_margin)} USDT`,
                                ],
                                [
                                    "Ganancia/rejilla",
                                    `${bot.profit_per_grid}%`,
                                ],
                                [
                                    "Comisión/rejilla",
                                    `${bot.commission_per_grid}%`,
                                ],
                                [
                                    "Precio est. liquidación",
                                    bot.est_liquidation_price > 0
                                        ? `${formatCurrency(bot.est_liquidation_price)} USDT`
                                        : "N/A",
                                ],
                                ["Deslizamiento", `${bot.slippage}%`],
                                [
                                    "Cuenta Binance",
                                    bot.binance_account?.label || "-",
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label as string}
                                    className="flex items-center justify-between py-3"
                                >
                                    <span className="text-sm text-muted-foreground">
                                        {label}
                                    </span>
                                    <span className="text-sm font-medium">
                                        {value}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="pnl" className="space-y-4">
                    {/* PNL Breakdown Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Desglose de PNL</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {(() => {
                                const grossGrid = Number(bot.grid_profit) + Number(bot.total_fees);
                                const netTotal = Number(bot.total_pnl);
                                const rows = [
                                    { label: "Grid Profit Bruto", value: grossGrid, hint: "Grid neto + comisiones" },
                                    { label: "Comisiones (est.)", value: -Number(bot.total_fees), color: "text-orange-500", hint: "Taker 0.04% × 2 sides" },
                                    { label: "Grid Profit Neto", value: Number(bot.grid_profit), bold: true },
                                    { label: "Unrealized / Trend PNL", value: Number(bot.trend_pnl), hint: "Posición abierta en Binance" },
                                    { label: "Funding Fees", value: null as number | null, hint: "No disponible en testnet" },
                                    { label: "PNL Neto Total", value: netTotal, bold: true, big: true },
                                ];
                                return rows.map((row) => (
                                    <div key={row.label} className={`flex items-center justify-between py-3 ${row.big ? "pt-4" : ""}`}>
                                        <div>
                                            <span className={`text-sm ${row.bold ? "font-semibold" : "text-muted-foreground"}`}>
                                                {row.label}
                                            </span>
                                            {row.hint && (
                                                <span className="ml-2 text-xs text-muted-foreground/60">{row.hint}</span>
                                            )}
                                        </div>
                                        <span className={`text-sm font-mono tabular-nums ${row.big ? "text-lg font-bold" : "font-medium"} ${
                                            row.color ? row.color
                                            : row.value === null ? "text-muted-foreground"
                                            : row.value > 0 ? "text-green-500"
                                            : row.value < 0 ? "text-destructive"
                                            : ""
                                        }`}>
                                            {row.value === null
                                                ? "N/D"
                                                : `${row.value > 0 ? "+" : ""}${formatCurrency(row.value)} USDT`}
                                        </span>
                                    </div>
                                ));
                            })()}
                            {Number(bot.real_investment) > 0 && (() => {
                                const roi = (Number(bot.total_pnl) / Number(bot.real_investment)) * 100;
                                return (
                                    <div className="flex items-center justify-between py-3">
                                        <span className="text-sm text-muted-foreground">ROI sobre inversión real</span>
                                        <span className={`text-sm font-bold ${roi >= 0 ? "text-green-500" : "text-destructive"}`}>
                                            {formatPercent(roi)}
                                        </span>
                                    </div>
                                );
                            })()}
                        </CardContent>
                    </Card>
                    {/* Drawdown / Risk Card */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Drawdown / Riesgo</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {drawdown.snapshots_used > 0 ? (
                                <>
                                    {[
                                        { label: "Peak PNL", value: drawdown.peak_pnl, hint: "Máximo PNL alcanzado" },
                                        { label: "PNL actual", value: drawdown.current_pnl },
                                        { label: "Max Drawdown", value: -drawdown.max_drawdown, color: "text-destructive", bold: true, hint: "Caída máxima desde peak" },
                                        { label: "Max Drawdown %", pct: drawdown.max_drawdown_pct, color: "text-destructive", bold: true, hint: "Sobre equity en peak" },
                                    ].map((row) => (
                                        <div key={row.label} className="flex items-center justify-between py-3">
                                            <div>
                                                <span className={`text-sm ${row.bold ? "font-semibold" : "text-muted-foreground"}`}>
                                                    {row.label}
                                                </span>
                                                {row.hint && (
                                                    <span className="ml-2 text-xs text-muted-foreground/60">{row.hint}</span>
                                                )}
                                            </div>
                                            <span className={`text-sm font-mono tabular-nums font-medium ${row.color ?? (
                                                (row.value ?? 0) > 0 ? "text-green-500" : (row.value ?? 0) < 0 ? "text-destructive" : ""
                                            )}`}>
                                                {"pct" in row
                                                    ? `-${Number(row.pct).toFixed(2)}%`
                                                    : `${Number(row.value) > 0 ? "+" : ""}${formatCurrency(Number(row.value))} USDT`}
                                            </span>
                                        </div>
                                    ))}
                                    {drawdown.drawdown_duration_minutes != null && (
                                        <div className="flex items-center justify-between py-3">
                                            <div>
                                                <span className="text-sm text-muted-foreground">Duración max drawdown</span>
                                                <span className="ml-2 text-xs text-muted-foreground/60">Tiempo en caída más larga</span>
                                            </div>
                                            <span className="text-sm font-mono tabular-nums font-medium">
                                                {drawdown.drawdown_duration_minutes >= 60
                                                    ? `${Math.floor(drawdown.drawdown_duration_minutes / 60)}h ${drawdown.drawdown_duration_minutes % 60}m`
                                                    : `${drawdown.drawdown_duration_minutes}m`}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between py-3">
                                        <span className="text-xs text-muted-foreground">
                                            Basado en {drawdown.snapshots_used} snapshots desde {drawdown.data_since ? new Date(drawdown.data_since).toLocaleDateString("es-AR", { day: "2-digit", month: "2-digit", year: "numeric" }) : "—"}
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <div className="flex h-24 items-center justify-center text-sm text-muted-foreground">
                                    Sin datos suficientes para calcular drawdown
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Risk Guard Status v2 */}
                    <Card className={riskGuard.level === "hard" ? "border-destructive" : riskGuard.level === "soft" ? "border-amber-500" : ""}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <Shield className="h-4 w-4" />
                                Risk Guard
                                {riskGuard.level === "hard" ? (
                                    <Badge variant="destructive" className="ml-auto text-[10px]">HARD GUARD</Badge>
                                ) : riskGuard.level === "soft" ? (
                                    <Badge className="ml-auto text-[10px] bg-amber-500/20 text-amber-500 border-amber-500/30">PROTEGIDO</Badge>
                                ) : (
                                    <Badge variant="outline" className="ml-auto text-[10px] border-green-500 text-green-500">ACTIVO</Badge>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0 divide-y">
                            {riskGuard.is_triggered && riskGuard.reason && (
                                <div className="pb-3">
                                    <div className={`rounded-md p-3 border ${riskGuard.level === "soft" ? "bg-amber-500/10 border-amber-500/20" : "bg-destructive/10 border-destructive/20"}`}>
                                        <p className={`text-sm font-medium ${riskGuard.level === "soft" ? "text-amber-500" : "text-destructive"}`}>{riskGuard.reason}</p>
                                        {riskGuard.triggered_at && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {new Date(riskGuard.triggered_at).toLocaleString("es-AR")}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}
                            {bot.status === "stopped" && riskGuard.stop_reason === "risk_guard" && (
                                <div className="py-3 space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className="text-[10px] border-orange-500 text-orange-500">
                                            Detenido por Risk Guard
                                        </Badge>
                                        {riskGuard.reentry_enabled && (
                                            <Badge variant="outline" className="text-[10px] border-blue-500 text-blue-500">
                                                Re-entry auto
                                            </Badge>
                                        )}
                                    </div>
                                    {riskGuard.reentry_last_block_reason && (
                                        <p className="text-xs text-muted-foreground">
                                            Último bloqueo: {riskGuard.reentry_last_block_reason}
                                        </p>
                                    )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full gap-1.5 text-xs border-emerald-500/50 text-emerald-500 hover:bg-emerald-500/10"
                                        onClick={() => router.post(`/bots/${bot.id}/reentry`)}
                                    >
                                        <RotateCcw className="h-3 w-3" /> Intentar re-entry
                                    </Button>
                                </div>
                            )}
                            {[
                                { label: "Soft Guard", value: `${riskGuard.effective_config.soft_guard_drawdown_pct ?? 15}%` },
                                { label: "Hard Guard", value: `${riskGuard.effective_config.hard_guard_drawdown_pct ?? 20}%` },
                                { label: "Modo drawdown", value: (riskGuard.effective_config.drawdown_mode ?? "peak_equity_drawdown") === "initial_capital_loss" ? "S/ capital" : "Desde pico" },
                                { label: "Acción hard", value: { stop_bot_only: "Detener", close_position_and_stop: "Cerrar + detener", pause_and_rebuild: "Pausar + rebuild", notify_only: "Solo notificar" }[riskGuard.effective_config.hard_guard_action ?? "pause_and_rebuild"] ?? "Pausar + rebuild" },
                                { label: "Dist. mín. liquidación", value: `${riskGuard.effective_config.min_liquidation_distance_pct ?? 15}%` },
                                { label: "Max fuera de rango", value: `${riskGuard.effective_config.max_price_out_of_range_pct ?? 5}%` },
                                { label: "Max errores", value: String(riskGuard.effective_config.max_consecutive_errors ?? 5) },
                                { label: "Max rebuilds/hora", value: String(riskGuard.effective_config.max_grid_rebuilds_per_hour ?? 3) },
                            ].map((row) => (
                                <div key={row.label} className="flex items-center justify-between py-2.5">
                                    <span className="text-sm text-muted-foreground">{row.label}</span>
                                    <span className="text-sm font-mono tabular-nums font-medium">{row.value}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                PNL Acumulado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {pnlHistory.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <AreaChart data={pnlHistory}>
                                            <defs>
                                                <linearGradient
                                                    id="pnlTabGreen"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="#22a962"
                                                        stopOpacity={0.3}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="#22a962"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                                <linearGradient
                                                    id="pnlTabBlue"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="#3b82f6"
                                                        stopOpacity={0.3}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="#3b82f6"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                stroke="hsl(var(--border))"
                                            />
                                            <XAxis
                                                dataKey="time"
                                                tick={{
                                                    fill: "hsl(var(--muted-foreground))",
                                                    fontSize: 10,
                                                }}
                                                interval="preserveStartEnd"
                                                minTickGap={40}
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: "hsl(var(--muted-foreground))",
                                                    fontSize: 10,
                                                }}
                                                tickFormatter={(v) => `${v >= 0 ? "+" : ""}${formatCurrency(v)}`}
                                            />
                                            <Tooltip
                                                contentStyle={{
                                                    backgroundColor:
                                                        "hsl(var(--card))",
                                                    border: "1px solid hsl(var(--border))",
                                                    borderRadius: "8px",
                                                    fontSize: "12px",
                                                }}
                                                formatter={(value, name) => [
                                                    `${(Number(value) ?? 0) >= 0 ? "+" : ""}${formatCurrency(Number(value) ?? 0)} USDT`,
                                                    String(name ?? "PNL"),
                                                ]}
                                                labelFormatter={(label) => `Fecha: ${label}`}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="total_pnl"
                                                stroke="#22a962"
                                                fill="url(#pnlTabGreen)"
                                                strokeWidth={2}
                                                name="PNL Total"
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="grid_profit"
                                                stroke="#3b82f6"
                                                fill="url(#pnlTabBlue)"
                                                strokeWidth={2}
                                                name="Grid Profit"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="flex h-72 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                                    <p>Sin datos de PNL aún</p>
                                    <p className="text-xs">
                                        Los datos aparecerán cuando el bot esté activo y se registren snapshots
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Últimas ejecuciones (desglose por transacción)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentFills.length > 0 ? (
                                <div className="max-h-80 overflow-y-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-muted-foreground">
                                                <th className="pb-2 text-left font-medium">Fecha</th>
                                                <th className="pb-2 text-left font-medium">Lado</th>
                                                <th className="pb-2 text-left font-medium">Precio</th>
                                                <th className="pb-2 text-right font-medium">Cantidad</th>
                                                <th className="pb-2 text-right font-medium">Bruto</th>
                                                <th className="pb-2 text-right font-medium">Fee</th>
                                                <th className="pb-2 text-right font-medium">Neto</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {recentFills.map((fill) => {
                                                const fee = fill.fee ?? 0;
                                                const gross = fill.pnl + fee;
                                                return (
                                                <tr key={fill.id} className="hover:bg-accent/50">
                                                    <td className="py-2 text-muted-foreground">
                                                        {fill.filled_at_fmt}
                                                    </td>
                                                    <td className="py-2">
                                                        <span className={fill.side === "buy" ? "text-green-500" : "text-red-500"}>
                                                            {fill.side === "buy" ? "Compra" : "Venta"}
                                                        </span>
                                                    </td>
                                                    <td className="py-2 font-mono tabular-nums">
                                                        {formatCurrency(fill.price, 2)}
                                                    </td>
                                                    <td className="py-2 text-right tabular-nums">
                                                        {fill.quantity.toFixed(5)}
                                                    </td>
                                                    <td className="py-2 text-right font-mono tabular-nums">
                                                        {fill.side === "sell" ? (
                                                            <span className={gross > 0 ? "text-green-500" : "text-muted-foreground"}>
                                                                {gross > 0 ? "+" : ""}{formatCurrency(gross)}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right font-mono tabular-nums">
                                                        {fee > 0 ? (
                                                            <span className="text-orange-500">-{formatCurrency(fee)}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-right font-mono tabular-nums">
                                                        <span
                                                            className={
                                                                fill.pnl > 0
                                                                    ? "text-green-500 font-medium"
                                                                    : fill.pnl < 0
                                                                      ? "text-destructive font-medium"
                                                                      : "text-muted-foreground"
                                                            }
                                                        >
                                                            {fill.pnl > 0 ? "+" : ""}
                                                            {fill.pnl !== 0 ? formatCurrency(fill.pnl) : "—"}
                                                        </span>
                                                    </td>
                                                </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="flex h-32 items-center justify-center text-sm text-muted-foreground">
                                    Sin ejecuciones aún
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {agentImpact && (
                        <AgentImpactPanel impact={agentImpact} />
                    )}
                </TabsContent>

                <TabsContent value="ai" className="space-y-4">
                    <AiPromptConfig key={`${bot.id}_${bot.ai_system_prompt?.slice(0, 20) ?? 'null'}_${bot.ai_agent_enabled}`} bot={bot} />
                </TabsContent>

                <TabsContent value="historial" className="space-y-4">
                    <BotActivityLog logs={activityLogs} />
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

