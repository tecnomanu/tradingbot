import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatCurrency } from "@/utils/formatters";
import { Head, Link } from "@inertiajs/react";
import {
    Activity,
    ArrowUpRight,
    Clock,
    Grid3x3,
    TrendingUp,
} from "lucide-react";
import { OrdersLayout } from "./OrdersLayout";

interface Position {
    id: number;
    symbol: string;
    side: string;
    leverage: number;
    investment: number;
    pnl: number;
    grid_profit: number;
    trend_pnl: number;
    liquidation_price: number;
    price_lower: number;
    price_upper: number;
    grid_count: number;
    total_rounds: number;
    rounds_24h: number;
    started_at: string | null;
    open_orders_count: number;
    filled_orders_count: number;
}

interface PositionsProps {
    positions: Position[];
}

function timeRunning(dateStr: string | null): string {
    if (!dateStr) return "-";
    const ms = Date.now() - new Date(dateStr).getTime();
    const hours = Math.floor(ms / 3600000);
    const mins = Math.floor((ms % 3600000) / 60000);
    if (hours > 24) {
        const days = Math.floor(hours / 24);
        return `${days}d ${hours % 24}h`;
    }
    return `${hours}h ${mins}m`;
}

export default function Positions({ positions }: PositionsProps) {
    const totalPnl = positions.reduce((sum, p) => sum + Number(p.pnl), 0);
    const totalInvestment = positions.reduce((sum, p) => sum + Number(p.investment), 0);

    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Posiciones Activas" />
            <OrdersLayout>
                <div className="p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h1 className="text-sm font-semibold">
                            Posiciones Activas ({positions.length})
                        </h1>
                        {positions.length > 0 && (
                            <div className="flex items-center gap-4 text-xs">
                                <span className="text-muted-foreground">
                                    Inversión total:{" "}
                                    <span className="font-semibold text-foreground">
                                        {formatCurrency(totalInvestment)} USDT
                                    </span>
                                </span>
                                <span className="text-muted-foreground">
                                    PNL total:{" "}
                                    <span
                                        className={`font-bold ${totalPnl >= 0 ? "text-green-500" : "text-red-500"}`}
                                    >
                                        {totalPnl >= 0 ? "+" : ""}
                                        {formatCurrency(totalPnl)} USDT
                                    </span>
                                </span>
                            </div>
                        )}
                    </div>

                    {positions.length === 0 ? (
                        <div className="flex flex-col items-center py-16 text-sm text-muted-foreground">
                            No hay posiciones activas.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {positions.map((pos) => {
                                const pnlPct =
                                    pos.investment > 0
                                        ? (pos.pnl / pos.investment) * 100
                                        : 0;
                                const sideLbl =
                                    pos.side === "long"
                                        ? "Largo"
                                        : pos.side === "short"
                                          ? "Corto"
                                          : "Neutral";
                                const baseCoin = pos.symbol
                                    .replace("USDT", "")
                                    .toLowerCase();

                                return (
                                    <Card
                                        key={pos.id}
                                        className="hover:border-primary/30 transition-colors"
                                    >
                                        <CardContent className="pt-4 pb-3">
                                            {/* Header */}
                                            <div className="flex items-center justify-between mb-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="relative flex h-2 w-2">
                                                        <span className="absolute inline-flex h-full w-full motion-safe:animate-ping rounded-full bg-green-400 opacity-75" />
                                                        <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                                                    </span>
                                                    <img
                                                        src={`https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${baseCoin}.png`}
                                                        alt=""
                                                        className="w-5 h-5 rounded-full"
                                                        onError={(e) => {
                                                            (
                                                                e.target as HTMLImageElement
                                                            ).style.display =
                                                                "none";
                                                        }}
                                                    />
                                                    <Link
                                                        href={`/bots/${pos.id}`}
                                                        className="text-sm font-bold hover:text-primary transition-colors"
                                                    >
                                                        {pos.symbol.replace(
                                                            "USDT",
                                                            "/USDT",
                                                        )}{" "}
                                                        Grid Bot de Futuros
                                                    </Link>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px] font-normal"
                                                    >
                                                        {timeRunning(
                                                            pos.started_at,
                                                        )}
                                                    </Badge>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-[10px]"
                                                    >
                                                        {pos.leverage}x{" "}
                                                        {sideLbl}
                                                    </Badge>
                                                    <Link
                                                        href={`/bots/${pos.id}`}
                                                        aria-label={`Ver detalles del bot ${pos.symbol.replace("USDT", "/USDT")}`}
                                                        className="text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    >
                                                        <ArrowUpRight className="h-3.5 w-3.5" />
                                                    </Link>
                                                </div>
                                            </div>

                                            {/* Stats Row 1 */}
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mb-3">
                                                <div>
                                                    <p className="text-muted-foreground mb-0.5">
                                                        Inversión real
                                                    </p>
                                                    <p className="font-semibold tabular-nums">
                                                        {formatCurrency(
                                                            pos.investment,
                                                        )}{" "}
                                                        USDT
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground mb-0.5">
                                                        PNL Total
                                                    </p>
                                                    <p
                                                        className={`font-bold tabular-nums ${Number(pos.pnl) >= 0 ? "text-green-500" : "text-red-500"}`}
                                                    >
                                                        <span className="sr-only">{Number(pos.pnl) >= 0 ? "Ganancia" : "Pérdida"}:</span>
                                                        {Number(pos.pnl) >= 0
                                                            ? "+"
                                                            : ""}
                                                        {formatCurrency(
                                                            pos.pnl,
                                                        )}{" "}
                                                        USDT (
                                                        {pnlPct >= 0 ? "+" : ""}
                                                        {pnlPct.toFixed(2)}%)
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground mb-0.5">
                                                        Ganancia Grid
                                                    </p>
                                                    <p className="font-semibold tabular-nums text-primary">
                                                        {formatCurrency(
                                                            pos.grid_profit,
                                                        )}{" "}
                                                        USDT
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground mb-0.5">
                                                        Trend PNL
                                                    </p>
                                                    <p
                                                        className={`font-semibold tabular-nums ${pos.trend_pnl >= 0 ? "text-green-500" : "text-red-500"}`}
                                                    >
                                                        {pos.trend_pnl >= 0
                                                            ? "+"
                                                            : ""}
                                                        {formatCurrency(
                                                            pos.trend_pnl,
                                                        )}{" "}
                                                        USDT
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Stats Row 2 */}
                                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 text-[11px] text-muted-foreground border-t pt-2">
                                                <div className="flex items-center gap-1">
                                                    <Grid3x3 className="h-3 w-3" />
                                                    <span>
                                                        Rango:{" "}
                                                        {formatCurrency(
                                                            pos.price_lower,
                                                            0,
                                                        )}{" "}
                                                        -{" "}
                                                        {formatCurrency(
                                                            pos.price_upper,
                                                            0,
                                                        )}{" "}
                                                        ({pos.grid_count}{" "}
                                                        rejillas)
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Activity className="h-3 w-3" />
                                                    <span>
                                                        Órdenes:{" "}
                                                        {pos.open_orders_count ??
                                                            0}{" "}
                                                        abiertas /{" "}
                                                        {pos.filled_orders_count ??
                                                            0}{" "}
                                                        ejecutadas
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <TrendingUp className="h-3 w-3" />
                                                    <span>
                                                        Rondas:{" "}
                                                        {pos.total_rounds}{" "}
                                                        total /{" "}
                                                        {pos.rounds_24h} 24h
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    <span>
                                                        Activo{" "}
                                                        {timeRunning(
                                                            pos.started_at,
                                                        )}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span>
                                                        Precio liq.:{" "}
                                                        {pos.liquidation_price >
                                                        0
                                                            ? formatCurrency(
                                                                  pos.liquidation_price,
                                                                  1,
                                                              ) + " USDT"
                                                            : "N/A"}
                                                    </span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}
