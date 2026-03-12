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
import { Separator } from "@/components/ui/separator";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Bot } from "@/types/bot";
import { formatCurrency, sideLabel, statusLabel } from "@/utils/formatters";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ChevronRight,
    Pencil,
    Play,
    Square,
} from "lucide-react";
import { OrdersLayout } from "./OrdersLayout";

interface BotsPageProps {
    activeBots: Bot[];
    stoppedBots: Bot[];
}

export default function Bots({ activeBots, stoppedBots }: BotsPageProps) {
    const allBots = [...activeBots, ...stoppedBots];
    const totalInvestment = activeBots.reduce(
        (s, b) => s + b.real_investment,
        0,
    );
    const totalPnl = activeBots.reduce((s, b) => s + b.total_pnl, 0);

    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Grid Bots" />
            <OrdersLayout>
                <div className="flex h-full flex-col">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b px-5 py-3">
                        <div className="flex items-center gap-4">
                            <h2 className="text-sm font-semibold">
                                Grid Bots
                            </h2>
                            {activeBots.length > 0 && (
                                <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                    <span>
                                        <span className="text-foreground font-medium">
                                            {activeBots.length}
                                        </span>{" "}
                                        activos
                                    </span>
                                    <Separator
                                        orientation="vertical"
                                        className="h-3"
                                    />
                                    <span>
                                        Inversión:{" "}
                                        <span className="text-foreground font-medium tabular-nums">
                                            {formatCurrency(totalInvestment)}{" "}
                                            USDT
                                        </span>
                                    </span>
                                    <Separator
                                        orientation="vertical"
                                        className="h-3"
                                    />
                                    <span>
                                        PNL:{" "}
                                        <span
                                            className={`font-bold tabular-nums ${totalPnl >= 0 ? "text-green-500" : "text-red-500"}`}
                                        >
                                            {totalPnl >= 0 ? "+" : ""}
                                            {formatCurrency(totalPnl)} USDT
                                        </span>
                                    </span>
                                </div>
                            )}
                        </div>
                        <span className="text-xs text-muted-foreground">
                            Todos ({allBots.length})
                        </span>
                    </div>

                    {/* Bot list */}
                    {allBots.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 text-center">
                            <p className="text-sm text-muted-foreground">
                                No hay bots creados
                            </p>
                            <Button asChild size="sm" className="mt-4">
                                <Link href="/bots">Crear bot</Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="divide-y overflow-auto">
                            {activeBots.map((bot) => (
                                <BotCard key={bot.id} bot={bot} />
                            ))}
                            {stoppedBots.map((bot) => (
                                <BotCard key={bot.id} bot={bot} />
                            ))}
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}

function BotCard({ bot }: { bot: Bot }) {
    const pnlPct =
        bot.real_investment > 0
            ? (bot.total_pnl / bot.real_investment) * 100
            : 0;
    const gridPnlPct =
        bot.real_investment > 0
            ? (bot.grid_profit / bot.real_investment) * 100
            : 0;
    const trendPnlPct =
        bot.real_investment > 0
            ? (bot.trend_pnl / bot.real_investment) * 100
            : 0;
    const symbolDisplay = bot.symbol.replace("USDT", "/USDT");
    const baseCoin = bot.symbol.replace("USDT", "").toLowerCase();
    const isActive = bot.status === "active";
    const isError = bot.status === "error";

    return (
        <div
            className={`px-5 py-4 transition-colors hover:bg-muted/20 ${isError ? "border-l-2 border-l-destructive" : ""}`}
        >
            {/* Header */}
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2.5">
                    {isActive && (
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                            <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                        </span>
                    )}
                    {isError && (
                        <AlertTriangle className="h-3.5 w-3.5 text-destructive" />
                    )}
                    <img
                        src={`https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${baseCoin}.png`}
                        alt={baseCoin}
                        className="w-5 h-5 rounded-full"
                        onError={(e) => {
                            (e.target as HTMLImageElement).style.display =
                                "none";
                        }}
                    />
                    <span className="text-sm font-bold">{symbolDisplay}</span>
                    <span className="text-xs text-muted-foreground">
                        Grid Bot
                    </span>
                    {!isActive && (
                        <Badge
                            variant={isError ? "destructive" : "secondary"}
                            className="text-[10px]"
                        >
                            {statusLabel(bot.status)}
                        </Badge>
                    )}
                </div>
                <Badge variant="outline" className="text-[10px] font-semibold">
                    {bot.leverage}x {sideLabel(bot.side)}
                </Badge>
            </div>

            {/* Stats row 1 */}
            <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                <div>
                    <span className="text-muted-foreground">
                        Margen real (USDT)
                    </span>
                    <p className="font-semibold tabular-nums mt-0.5">
                        {formatCurrency(bot.real_investment)}
                    </p>
                </div>
                <div className="text-right">
                    <span className="text-muted-foreground">
                        Beneficio total(USDT)
                    </span>
                    <p
                        className={`font-bold tabular-nums mt-0.5 ${bot.total_pnl >= 0 ? "text-green-500" : "text-red-500"}`}
                    >
                        {bot.total_pnl >= 0 ? "+" : ""}
                        {formatCurrency(bot.total_pnl)}(
                        {pnlPct >= 0 ? "+" : ""}
                        {pnlPct.toFixed(2)}%)
                    </p>
                </div>
            </div>

            {/* Stats row 2 */}
            <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-xs mt-2">
                <div>
                    <span className="text-muted-foreground">
                        Ganancia rejilla(USDT)
                    </span>
                    <p className="text-primary tabular-nums mt-0.5">
                        +{formatCurrency(bot.grid_profit)}(+
                        {gridPnlPct.toFixed(2)}%)
                    </p>
                </div>
                <div className="text-right">
                    <span className="text-muted-foreground">Trend PNL(USDT)</span>
                    <p
                        className={`tabular-nums mt-0.5 ${bot.trend_pnl >= 0 ? "text-green-500" : "text-red-500"}`}
                    >
                        {bot.trend_pnl >= 0 ? "+" : ""}
                        {formatCurrency(bot.trend_pnl)}(
                        {trendPnlPct >= 0 ? "+" : ""}
                        {trendPnlPct.toFixed(2)}%)
                    </p>
                </div>
            </div>

            {/* Range & rounds */}
            <div className="mt-3 flex items-center gap-3 text-[11px] text-muted-foreground">
                <span>
                    {formatCurrency(bot.price_lower)} ~{" "}
                    {formatCurrency(bot.price_upper)} ({bot.grid_count}{" "}
                    rejillas)
                </span>
                <Separator orientation="vertical" className="h-3" />
                <span>
                    {bot.rounds_24h ?? 0} rondas 24h ({bot.total_rounds} total)
                </span>
            </div>

            {/* Order counts + uptime */}
            <div className="mt-2 flex items-center gap-3 text-[11px] text-muted-foreground">
                <span>
                    Órdenes: {bot.open_orders_count ?? 0} abiertas /{" "}
                    {bot.filled_orders_count ?? 0} ejecutadas
                </span>
                {bot.started_at && (
                    <>
                        <Separator orientation="vertical" className="h-3" />
                        <span>
                            Activo desde{" "}
                            {new Date(bot.started_at).toLocaleDateString()}
                        </span>
                    </>
                )}
            </div>

            {/* Bottom row */}
            <div className="mt-3 grid grid-cols-3 gap-2 text-[11px]">
                <div>
                    <span className="text-muted-foreground">
                        Inv. total (capital + margen)
                    </span>
                    <p className="tabular-nums">
                        {formatCurrency(bot.investment)}
                    </p>
                </div>
                <div className="text-center">
                    <span className="text-muted-foreground">
                        Precio liquidación
                    </span>
                    <p className="tabular-nums">
                        {formatCurrency(bot.est_liquidation_price)}
                    </p>
                </div>
                <div className="text-right">
                    <span className="text-muted-foreground">
                        Margen adicional
                    </span>
                    <p className="tabular-nums">
                        {formatCurrency(bot.additional_margin)}
                    </p>
                </div>
            </div>

            {/* Actions */}
            <div className="mt-3 flex gap-2">
                {isActive ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                            >
                                <Square className="mr-1 h-3 w-3" /> Detener
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Detener bot {symbolDisplay}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Se cancelarán todas las órdenes abiertas y
                                    el bot dejará de operar.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        router.post(`/bots/${bot.id}/stop`)
                                    }
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                >
                                    Detener bot
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                ) : (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="sm" className="h-7 text-xs">
                                <Play className="mr-1 h-3 w-3" /> Iniciar
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Iniciar bot {symbolDisplay}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    El bot comenzará a operar y colocará
                                    órdenes en Binance.
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
                )}
                <Button variant="ghost" size="sm" className="h-7 text-xs" asChild>
                    <Link href={`/bots/${bot.id}/edit`}>
                        <Pencil className="mr-1 h-3 w-3" /> Editar
                    </Link>
                </Button>
                <Button variant="ghost" size="sm" className="h-7 text-xs" asChild>
                    <Link href={`/bots/${bot.id}`}>
                        Detalles <ChevronRight className="ml-1 h-3 w-3" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}
