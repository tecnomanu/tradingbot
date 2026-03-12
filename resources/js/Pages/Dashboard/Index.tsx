import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Bot, BotPnlSnapshot, DashboardStats } from "@/types/bot";
import { formatCurrency } from "@/utils/formatters";
import { Head, Link } from "@inertiajs/react";
import {
    Activity,
    ArrowUpRight,
    Bot as BotIcon,
    Brain,
    Clock,
    Grid3x3,
    ShoppingCart,
    TrendingUp,
    Wallet,
    Zap,
} from "lucide-react";
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

interface ExtendedStats {
    total_orders: number;
    open_orders: number;
    filled_orders: number;
    filled_24h: number;
    rounds_24h: number;
    accounts_total: number;
    accounts_active: number;
    accounts_testnet: number;
    ai_conversations: number;
    ai_actions: number;
    last_ai_consult: string | null;
    total_bots_stopped: number;
    total_bots_error: number;
    trend_pnl: number;
}

interface RecentOrder {
    id: number;
    symbol: string;
    side: string;
    price: number;
    quantity: number;
    pnl: number;
    filled_at: string;
}

interface RecentAction {
    id: number;
    symbol: string;
    action: string;
    source: string;
    created_at: string;
}

interface DashboardProps {
    stats: DashboardStats;
    activeBots: Bot[];
    pnlChart: BotPnlSnapshot[];
    extended: ExtendedStats;
    recentOrders: RecentOrder[];
    recentActions: RecentAction[];
}

function timeSince(dateStr: string | null): string {
    if (!dateStr) return "Nunca";
    const ms = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return "Ahora";
    if (mins < 60) return `${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h`;
    return `${Math.floor(hrs / 24)}d`;
}

function MiniStat({
    label,
    value,
    sub,
    color,
    icon: Icon,
    href,
}: {
    label: string;
    value: string;
    sub?: string;
    color?: string;
    icon: React.ElementType;
    href?: string;
}) {
    const inner = (
        <div className="flex items-start gap-3">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted/50">
                <Icon className={`h-4 w-4 ${color || "text-muted-foreground"}`} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-[11px] text-muted-foreground truncate">
                    {label}
                </p>
                <p className={`text-lg font-bold leading-tight ${color || ""}`}>
                    {value}
                </p>
                {sub && (
                    <p className="text-[10px] text-muted-foreground mt-0.5">
                        {sub}
                    </p>
                )}
            </div>
        </div>
    );

    if (href) {
        return (
            <Link
                href={href}
                className="block rounded-lg border p-3 hover:border-primary/30 hover:bg-muted/20 transition-all"
            >
                {inner}
            </Link>
        );
    }

    return <div className="rounded-lg border p-3">{inner}</div>;
}

export default function Index({
    stats,
    activeBots,
    pnlChart,
    extended,
    recentOrders,
    recentActions,
}: DashboardProps) {
    const pnlPct =
        stats.total_investment > 0
            ? (stats.total_pnl / stats.total_investment) * 100
            : 0;

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div className="space-y-5">
                {/* Row 1: Key Metrics */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <MiniStat
                        label="Bots Activos"
                        value={`${stats.active_bots}`}
                        sub={`${stats.total_bots} total · ${extended.total_bots_stopped} stop · ${extended.total_bots_error} error`}
                        icon={BotIcon}
                        color="text-blue-500"
                        href="/bots"
                    />
                    <MiniStat
                        label="Inversión Activa"
                        value={`${formatCurrency(stats.total_investment)}`}
                        sub="USDT en operación"
                        icon={Wallet}
                        color="text-primary"
                    />
                    <MiniStat
                        label="PNL Total"
                        value={`${stats.total_pnl >= 0 ? "+" : ""}${formatCurrency(stats.total_pnl)}`}
                        sub={`${pnlPct >= 0 ? "+" : ""}${pnlPct.toFixed(2)}% ROI`}
                        icon={TrendingUp}
                        color={
                            stats.total_pnl >= 0
                                ? "text-green-500"
                                : "text-red-500"
                        }
                    />
                    <MiniStat
                        label="Ganancia Grid"
                        value={`${formatCurrency(stats.total_grid_profit)}`}
                        sub={`Trend: ${extended.trend_pnl >= 0 ? "+" : ""}${formatCurrency(extended.trend_pnl)} USDT`}
                        icon={Grid3x3}
                        color="text-emerald-500"
                    />
                    <MiniStat
                        label="Órdenes"
                        value={`${extended.open_orders}`}
                        sub={`abiertas · ${extended.filled_24h} exec 24h · ${extended.rounds_24h} rondas`}
                        icon={ShoppingCart}
                        color="text-orange-500"
                        href="/orders/bots"
                    />
                    <MiniStat
                        label="AI Agent"
                        value={`${extended.ai_conversations}`}
                        sub={`consultas · ${extended.ai_actions} acciones · ${timeSince(extended.last_ai_consult)} ago`}
                        icon={Brain}
                        color="text-purple-500"
                        href="/ai-agent"
                    />
                </div>

                {/* Row 2: Chart + Bots + Activity */}
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                    {/* PNL Chart */}
                    <Card className="lg:col-span-5">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">
                                Evolución PNL
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {pnlChart.length === 0 ? (
                                <div className="flex h-52 flex-col items-center justify-center text-muted-foreground">
                                    <Activity className="h-8 w-8 opacity-30 mb-2" />
                                    <p className="text-xs">
                                        Sin datos de PNL aún
                                    </p>
                                </div>
                            ) : (
                                <div className="h-52">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <AreaChart
                                            data={pnlChart}
                                            margin={{
                                                top: 5,
                                                right: 5,
                                                left: -15,
                                                bottom: 0,
                                            }}
                                        >
                                            <defs>
                                                <linearGradient
                                                    id="dashPnl"
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
                                                    id="dashGrid"
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
                                                    fontSize: 9,
                                                }}
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: "hsl(var(--muted-foreground))",
                                                    fontSize: 9,
                                                }}
                                            />
                                            <Tooltip
                                                contentStyle={{
                                                    backgroundColor:
                                                        "hsl(var(--card))",
                                                    border: "1px solid hsl(var(--border))",
                                                    borderRadius: "8px",
                                                    fontSize: "11px",
                                                }}
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="total_pnl"
                                                stroke="#22a962"
                                                fill="url(#dashPnl)"
                                                strokeWidth={2}
                                                name="PNL Total"
                                            />
                                            <Area
                                                type="monotone"
                                                dataKey="grid_profit"
                                                stroke="#3b82f6"
                                                fill="url(#dashGrid)"
                                                strokeWidth={2}
                                                name="Grid Profit"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                            <div className="mt-2 flex items-center gap-4 text-[10px] text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                    PNL Total
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="h-1.5 w-1.5 rounded-full bg-blue-500" />
                                    Grid
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Active Bots */}
                    <Card className="lg:col-span-4">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm">
                                Bots Activos ({activeBots.length})
                            </CardTitle>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 text-[10px]"
                                asChild
                            >
                                <Link href="/bots">Ver todos</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {activeBots.length === 0 ? (
                                <div className="flex flex-col items-center py-8 text-center">
                                    <BotIcon className="h-8 w-8 text-muted-foreground opacity-30 mb-2" />
                                    <p className="text-xs text-muted-foreground">
                                        Sin bots activos
                                    </p>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="mt-3 h-7 text-xs"
                                    >
                                        <Link href="/bots">Crear bot</Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {activeBots.map((bot) => (
                                        <Link
                                            key={bot.id}
                                            href={`/bots/${bot.id}`}
                                            className="flex items-center justify-between rounded-md border p-2.5 hover:bg-muted/30 transition-colors"
                                        >
                                            <div className="flex items-center gap-2.5">
                                                <div className="relative">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10 text-[10px] font-bold text-primary">
                                                        {bot.symbol.replace(
                                                            "USDT",
                                                            "",
                                                        )}
                                                    </div>
                                                    <span className="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                                        <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                                                    </span>
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-xs font-medium">
                                                            {bot.name}
                                                        </span>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[8px] h-4 px-1"
                                                        >
                                                            {bot.leverage}x
                                                        </Badge>
                                                    </div>
                                                    <p className="text-[10px] text-muted-foreground">
                                                        {bot.grid_count}{" "}
                                                        rejillas ·{" "}
                                                        {bot.total_rounds}{" "}
                                                        rondas
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p
                                                    className={`text-xs font-bold tabular-nums ${
                                                        bot.total_pnl >= 0
                                                            ? "text-green-500"
                                                            : "text-red-500"
                                                    }`}
                                                >
                                                    {bot.total_pnl >= 0
                                                        ? "+"
                                                        : ""}
                                                    {formatCurrency(
                                                        bot.total_pnl,
                                                    )}{" "}
                                                    USDT
                                                </p>
                                                <p className="text-[10px] text-muted-foreground">
                                                    {formatCurrency(
                                                        bot.real_investment,
                                                    )}{" "}
                                                    inv.
                                                </p>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Activity */}
                    <div className="lg:col-span-3 space-y-4">
                        {/* Recent Filled Orders */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm">
                                    Últimas Ejecuciones
                                </CardTitle>
                                <Link
                                    href="/orders/history"
                                    className="text-[10px] text-muted-foreground hover:text-foreground"
                                >
                                    Ver todas
                                    <ArrowUpRight className="inline h-3 w-3 ml-0.5" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {recentOrders.length === 0 ? (
                                    <p className="text-xs text-muted-foreground py-4 text-center">
                                        Sin ejecuciones recientes
                                    </p>
                                ) : (
                                    <div className="space-y-1.5">
                                        {recentOrders.map((order) => (
                                            <div
                                                key={order.id}
                                                className="flex items-center justify-between text-[11px] py-1 border-b border-dashed last:border-0"
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <Badge
                                                        variant={
                                                            order.side ===
                                                            "buy"
                                                                ? "default"
                                                                : "destructive"
                                                        }
                                                        className="text-[8px] h-4 px-1"
                                                    >
                                                        {order.side === "buy"
                                                            ? "C"
                                                            : "V"}
                                                    </Badge>
                                                    <span className="font-mono tabular-nums">
                                                        {formatCurrency(
                                                            order.price,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`font-medium tabular-nums ${
                                                            order.pnl >= 0
                                                                ? "text-green-500"
                                                                : "text-red-500"
                                                        }`}
                                                    >
                                                        {order.pnl > 0
                                                            ? "+"
                                                            : ""}
                                                        {formatCurrency(
                                                            order.pnl,
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {timeSince(
                                                            order.filled_at,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* AI Agent Activity */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm">
                                    AI Agent
                                </CardTitle>
                                <Link
                                    href="/ai-agent"
                                    className="text-[10px] text-muted-foreground hover:text-foreground"
                                >
                                    Panel AI
                                    <ArrowUpRight className="inline h-3 w-3 ml-0.5" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {recentActions.length === 0 ? (
                                    <div className="text-center py-3">
                                        <p className="text-xs text-muted-foreground">
                                            Sin acciones del agente
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        {recentActions.map((action) => (
                                            <div
                                                key={action.id}
                                                className="flex items-center justify-between text-[11px] py-1 border-b border-dashed last:border-0"
                                            >
                                                <div className="flex items-center gap-1.5">
                                                    <Zap className="h-3 w-3 text-purple-500" />
                                                    <span className="font-medium">
                                                        {action.action}
                                                    </span>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[8px] h-4 px-1"
                                                    >
                                                        {action.source}
                                                    </Badge>
                                                </div>
                                                <span className="text-muted-foreground">
                                                    {timeSince(
                                                        action.created_at,
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Accounts Summary */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm">
                                    Cuentas
                                </CardTitle>
                                <Link
                                    href="/binance-accounts"
                                    className="text-[10px] text-muted-foreground hover:text-foreground"
                                >
                                    Gestionar
                                    <ArrowUpRight className="inline h-3 w-3 ml-0.5" />
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-4 text-xs">
                                    <div>
                                        <p className="text-muted-foreground">
                                            Total
                                        </p>
                                        <p className="font-bold">
                                            {extended.accounts_total}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Activas
                                        </p>
                                        <p className="font-bold text-green-500">
                                            {extended.accounts_active}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Testnet
                                        </p>
                                        <p className="font-bold text-yellow-500">
                                            {extended.accounts_testnet}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Total órdenes
                                        </p>
                                        <p className="font-bold">
                                            {extended.total_orders}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
