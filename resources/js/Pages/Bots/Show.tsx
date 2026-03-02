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
    Bot,
    BotPnlSnapshot,
    GridConfig,
    Order,
    OrderStats,
} from "@/types/bot";
import {
    formatCurrency,
    formatDate,
    sideLabel,
    statusLabel,
} from "@/utils/formatters";
import { Head, Link, router } from "@inertiajs/react";
import {
    Activity,
    ArrowLeft,
    Brain,
    CheckCircle,
    Clock,
    FlaskConical,
    Loader2,
    Pencil,
    Play,
    Save,
    Trash2,
    XCircle,
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
import OrdersTable from "./components/OrdersTable";
import TradingViewChart, { ChartOrder } from "./components/TradingViewChart";

interface BinancePosition {
    symbol: string;
    positionAmt: number;
    entryPrice: number;
    unrealizedProfit: number;
    liquidationPrice: number;
    positionSide: string;
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
    position?: BinancePosition | null;
    activity: ActivityInfo;
    chartOrders?: ChartOrder[];
}

function timeSince(dateStr: string | null): string {
    if (!dateStr) return "Nunca";
    const ms = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return "Hace segundos";
    if (mins < 60) return `Hace ${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `Hace ${hrs}h ${mins % 60}m`;
    return `Hace ${Math.floor(hrs / 24)}d`;
}

export default function Show({
    bot,
    orderStats,
    gridConfig,
    orders,
    pnlHistory,
    position,
    activity,
    chartOrders = [],
}: ShowProps) {
    const isRunning = bot.status === "active";
    const hasRecentActivity =
        activity.last_order_at &&
        Date.now() - new Date(activity.last_order_at).getTime() < 300000;

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
                                    {bot.symbol.replace("USDT", "/USDT")} Grid
                                    Bot de Futuros
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
                                <Badge variant="outline">
                                    {bot.leverage}x {sideLabel(bot.side)}
                                </Badge>
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
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.post(`/bots/${bot.id}/start`)
                                    }
                                >
                                    <Play className="mr-1 h-3 w-3" /> Iniciar
                                </Button>
                            )
                        )}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive"
                            onClick={() => {
                                if (confirm("¿Eliminar este bot?"))
                                    router.delete(`/bots/${bot.id}`);
                            }}
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`Bot - ${bot.name}`} />

            {/* Activity Banner */}
            <div className="mb-4 flex items-center gap-3 rounded-lg border px-4 py-2.5 bg-card/50">
                <div className="flex items-center gap-2">
                    {isRunning ? (
                        hasRecentActivity ? (
                            <span className="relative flex h-2.5 w-2.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
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

            {/* Stats Banner */}
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-6">
                {[
                    {
                        label: "Inversión real",
                        value: `${formatCurrency(bot.real_investment)} USDT`,
                    },
                    {
                        label: "Beneficio total",
                        value: `${bot.total_pnl >= 0 ? "+" : ""}${formatCurrency(bot.total_pnl)} USDT`,
                        color:
                            bot.total_pnl >= 0
                                ? "text-green-500"
                                : "text-destructive",
                    },
                    {
                        label: "Ganancia rejilla",
                        value: `${formatCurrency(bot.grid_profit)} USDT`,
                        color: "text-primary",
                    },
                    {
                        label: "Trend PNL",
                        value: `${formatCurrency(bot.trend_pnl)} USDT`,
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
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
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
                        AI Agent
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
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5 text-xs">
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
                                                />
                                                <YAxis
                                                    tick={{
                                                        fill: "hsl(var(--muted-foreground))",
                                                        fontSize: 10,
                                                    }}
                                                />
                                                <Tooltip
                                                    contentStyle={{
                                                        backgroundColor:
                                                            "hsl(var(--card))",
                                                        border: "1px solid hsl(var(--border))",
                                                        borderRadius: "8px",
                                                        fontSize: "12px",
                                                    }}
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
                                    <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
                                        Sin datos de PNL aún
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {position && position.positionAmt !== 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    Posición en Binance
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                                    {[
                                        {
                                            label: "Tamaño",
                                            value: `${position.positionAmt} ${bot.symbol.replace("USDT", "")}`,
                                        },
                                        {
                                            label: "Precio entrada",
                                            value: `${formatCurrency(position.entryPrice, 2)} USDT`,
                                        },
                                        {
                                            label: "PNL no realizado",
                                            value: `${position.unrealizedProfit >= 0 ? "+" : ""}${formatCurrency(position.unrealizedProfit)} USDT`,
                                            color:
                                                position.unrealizedProfit >= 0
                                                    ? "text-green-500"
                                                    : "text-destructive",
                                        },
                                        {
                                            label: "Precio liquidación",
                                            value:
                                                position.liquidationPrice > 0
                                                    ? `${formatCurrency(position.liquidationPrice, 1)} USDT`
                                                    : "N/A",
                                        },
                                        {
                                            label: "Lado posición",
                                            value:
                                                position.positionAmt > 0
                                                    ? "LONG"
                                                    : "SHORT",
                                            color:
                                                position.positionAmt > 0
                                                    ? "text-green-500"
                                                    : "text-destructive",
                                        },
                                    ].map((item, i) => (
                                        <div key={i} className="space-y-1">
                                            <p className="text-xs text-muted-foreground">
                                                {item.label}
                                            </p>
                                            <p
                                                className={`text-sm font-semibold tabular-nums ${item.color || ""}`}
                                            >
                                                {item.value}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
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
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-xs text-muted-foreground">
                                    PNL Total
                                </p>
                                <p
                                    className={`mt-1 text-2xl font-bold ${bot.total_pnl >= 0 ? "text-green-500" : "text-destructive"}`}
                                >
                                    {bot.total_pnl >= 0 ? "+" : ""}
                                    {formatCurrency(bot.total_pnl)} USDT
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-xs text-muted-foreground">
                                    Ganancia Grid
                                </p>
                                <p className="mt-1 text-2xl font-bold text-primary">
                                    {formatCurrency(bot.grid_profit)} USDT
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 text-center">
                                <p className="text-xs text-muted-foreground">
                                    Trend PNL
                                </p>
                                <p
                                    className={`mt-1 text-2xl font-bold ${bot.trend_pnl >= 0 ? "text-green-500" : "text-destructive"}`}
                                >
                                    {bot.trend_pnl >= 0 ? "+" : ""}
                                    {formatCurrency(bot.trend_pnl)} USDT
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Historial PNL
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
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: "hsl(var(--muted-foreground))",
                                                    fontSize: 10,
                                                }}
                                            />
                                            <Tooltip
                                                contentStyle={{
                                                    backgroundColor:
                                                        "hsl(var(--card))",
                                                    border: "1px solid hsl(var(--border))",
                                                    borderRadius: "8px",
                                                    fontSize: "12px",
                                                }}
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
                                <div className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                                    Los datos aparecerán cuando el bot esté
                                    activo
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="ai" className="space-y-4">
                    <AiPromptConfig bot={bot} />
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

const DEFAULT_SYSTEM_PROMPT = `Grid trading bot advisor. Review bot state, analyze market, take action if needed.

Rules:
- Call get_bot_status + get_market_data first. Only call other tools if something looks off.
- Be conservative: act only with clear reason. Destructive actions need strong justification.
- Do NOT set SL/TP if already set at the same price. Only call set_stop_loss/set_take_profit when changing to a NEW value.
- Set/adjust SL/TP if: price near grid edge, RSI extreme (>70/<30), or negative unrealized PNL.
- If price outside grid range: critical, consider stopping.
- Call done when finished. In "analysis": write a human-readable market assessment in Spanish (2-3 sentences max explaining what you see and why you acted or not). In "summary": 1 short sentence. All prices USDT.`;

const DEFAULT_USER_PROMPT = `Review Bot #{bot_id} ({symbol}) at {now} UTC. Call get_bot_status and get_market_data. Only call other tools if something looks abnormal. Then call done with a brief summary.`;

function AiPromptConfig({ bot }: { bot: Bot }) {
    const [systemPrompt, setSystemPrompt] = useState(bot.ai_system_prompt ?? "");
    const [userPrompt, setUserPrompt] = useState(bot.ai_user_prompt ?? "");
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [review, setReview] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);

    const handleSave = () => {
        setSaving(true);
        setSaved(false);
        router.put(
            `/ai-agent/bots/${bot.id}/prompts`,
            { ai_system_prompt: systemPrompt, ai_user_prompt: userPrompt },
            {
                preserveScroll: true,
                onSuccess: () => setSaved(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleTest = async () => {
        setTesting(true);
        setReview(null);
        try {
            const res = await fetch(`/ai-agent/bots/${bot.id}/test-prompts`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "",
                },
                body: JSON.stringify({
                    ai_system_prompt: systemPrompt || null,
                    ai_user_prompt: userPrompt || null,
                }),
            });
            const data = await res.json();
            setReview(data.review || data.error || "Sin respuesta");
        } catch (e: any) {
            setReview("Error: " + e.message);
        } finally {
            setTesting(false);
        }
    };

    const handleReset = () => {
        setSystemPrompt(DEFAULT_SYSTEM_PROMPT);
        setUserPrompt(DEFAULT_USER_PROMPT);
        setSaved(false);
    };

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Brain className="h-5 w-5" />
                        Configuración del AI Agent
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Personalizá el comportamiento del agente para este bot.
                        Dejá vacío para usar la configuración por defecto.
                        Variables disponibles en el primer mensaje: <code className="text-xs bg-muted px-1 rounded">{"{bot_id}"}</code>, <code className="text-xs bg-muted px-1 rounded">{"{symbol}"}</code>, <code className="text-xs bg-muted px-1 rounded">{"{now}"}</code>
                    </p>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label htmlFor="system-prompt">System Prompt</Label>
                        <Textarea
                            id="system-prompt"
                            value={systemPrompt}
                            onChange={(e) => { setSystemPrompt(e.target.value); setSaved(false); }}
                            placeholder={DEFAULT_SYSTEM_PROMPT}
                            rows={10}
                            className="font-mono text-sm"
                        />
                        <p className="text-xs text-muted-foreground">
                            Define las reglas, personalidad y estrategia del agente. Ej: más agresivo, priorizar RSI+SMA, etc.
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="user-prompt">Primer Mensaje</Label>
                        <Textarea
                            id="user-prompt"
                            value={userPrompt}
                            onChange={(e) => { setUserPrompt(e.target.value); setSaved(false); }}
                            placeholder={DEFAULT_USER_PROMPT}
                            rows={4}
                            className="font-mono text-sm"
                        />
                        <p className="text-xs text-muted-foreground">
                            El mensaje inicial que recibe el agente en cada consulta.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button onClick={handleSave} disabled={saving} className="gap-1.5">
                            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            {saving ? "Guardando..." : "Guardar"}
                        </Button>
                        <Button onClick={handleTest} disabled={testing} variant="secondary" className="gap-1.5">
                            {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <FlaskConical className="h-4 w-4" />}
                            {testing ? "Analizando..." : "Test con IA"}
                        </Button>
                        <Button onClick={handleReset} variant="ghost" className="text-muted-foreground">
                            Restaurar por defecto
                        </Button>
                        {saved && (
                            <span className="flex items-center gap-1 text-sm text-emerald-400">
                                <CheckCircle className="h-4 w-4" /> Guardado
                            </span>
                        )}
                    </div>
                </CardContent>
            </Card>

            {review && (
                <Card className="border-primary/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FlaskConical className="h-4 w-4" />
                            Evaluación de la configuración
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="prose prose-sm prose-invert max-w-none whitespace-pre-wrap text-sm">
                            {review}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
