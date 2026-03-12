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
import { Card, CardContent } from "@/components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Bot } from "@/types/bot";
import { formatCurrency, sideLabel, statusLabel } from "@/utils/formatters";
import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowUpRight,
    Play,
    Square,
    Activity,
    TrendingUp,
    AlertTriangle,
} from "lucide-react";
import { OrdersLayout } from "./OrdersLayout";

interface BotsPageProps {
    activeBots: Bot[];
    stoppedBots: Bot[];
}

export default function Bots({ activeBots, stoppedBots }: BotsPageProps) {
    const totalInvestment = activeBots.reduce((s, b) => s + b.real_investment, 0);
    const totalPnl = activeBots.reduce((s, b) => s + b.total_pnl, 0);

    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Bots" />
            <OrdersLayout>
                <div className="p-5 space-y-6">
                    {/* Summary bar */}
                    {activeBots.length > 0 && (
                        <div className="flex flex-wrap items-center gap-6 text-xs">
                            <div>
                                <span className="text-muted-foreground">Activos</span>
                                <p className="text-lg font-bold">{activeBots.length}</p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Inversión total</span>
                                <p className="text-lg font-bold tabular-nums">
                                    {formatCurrency(totalInvestment)} USDT
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">PNL Total</span>
                                <p className={`text-lg font-bold tabular-nums ${totalPnl >= 0 ? "text-green-500" : "text-red-500"}`}>
                                    {totalPnl >= 0 ? "+" : ""}{formatCurrency(totalPnl)} USDT
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Active bots */}
                    <section>
                        <h2 className="text-sm font-semibold mb-3 flex items-center gap-2">
                            <Activity className="h-4 w-4 text-green-500" />
                            Bots activos ({activeBots.length})
                        </h2>
                        {activeBots.length === 0 ? (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    No hay bots activos.{" "}
                                    <Link href="/bots" className="text-primary hover:underline">
                                        Crear bot
                                    </Link>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="space-y-2">
                                {activeBots.map((bot) => (
                                    <BotRow key={bot.id} bot={bot} />
                                ))}
                            </div>
                        )}
                    </section>

                    {/* Stopped bots */}
                    {stoppedBots.length > 0 && (
                        <section>
                            <h2 className="text-sm font-semibold mb-3 flex items-center gap-2">
                                <Square className="h-4 w-4 text-muted-foreground" />
                                Bots detenidos ({stoppedBots.length})
                            </h2>
                            <div className="space-y-2">
                                {stoppedBots.map((bot) => (
                                    <BotRow key={bot.id} bot={bot} />
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}

function BotRow({ bot }: { bot: Bot }) {
    const symbolDisplay = bot.symbol.replace("USDT", "/USDT");
    const pnlPct = bot.real_investment > 0 ? (bot.total_pnl / bot.real_investment) * 100 : 0;
    const baseCoin = bot.symbol.replace("USDT", "").toLowerCase();
    const isActive = bot.status === "active";
    const isError = bot.status === "error";

    return (
        <Card className={`transition-colors hover:border-primary/30 ${isError ? "border-destructive/40" : ""}`}>
            <CardContent className="py-3 px-4">
                <div className="flex items-center gap-4">
                    {/* Icon + name */}
                    <div className="flex items-center gap-2 min-w-[200px]">
                        {isActive && (
                            <span className="relative flex h-2 w-2">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                            </span>
                        )}
                        {isError && <AlertTriangle className="h-3.5 w-3.5 text-destructive" />}
                        {!isActive && !isError && <Square className="h-3 w-3 text-muted-foreground" />}
                        <img
                            src={`https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${baseCoin}.png`}
                            alt={baseCoin}
                            className="w-5 h-5 rounded-full"
                            onError={(e) => { (e.target as HTMLImageElement).style.display = "none"; }}
                        />
                        <span className="text-sm font-bold">{symbolDisplay}</span>
                        <Badge variant="outline" className="text-[10px]">
                            {bot.leverage}x {sideLabel(bot.side)}
                        </Badge>
                        {!isActive && (
                            <Badge variant={isError ? "destructive" : "secondary"} className="text-[10px]">
                                {statusLabel(bot.status)}
                            </Badge>
                        )}
                    </div>

                    {/* Stats */}
                    <div className="flex-1 grid grid-cols-5 gap-4 text-xs">
                        <div>
                            <p className="text-muted-foreground">Inversión</p>
                            <p className="font-semibold tabular-nums">{formatCurrency(bot.real_investment)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">PNL</p>
                            <p className={`font-bold tabular-nums ${bot.total_pnl >= 0 ? "text-green-500" : "text-red-500"}`}>
                                {bot.total_pnl >= 0 ? "+" : ""}{formatCurrency(bot.total_pnl)}
                                <span className="font-normal ml-1">({pnlPct >= 0 ? "+" : ""}{pnlPct.toFixed(1)}%)</span>
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Grid Profit</p>
                            <p className="font-semibold tabular-nums text-primary">
                                {formatCurrency(bot.grid_profit)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Órdenes</p>
                            <p className="tabular-nums">
                                {bot.open_orders_count ?? 0} <span className="text-muted-foreground">abiertas</span>
                                {" / "}
                                {bot.filled_orders_count ?? 0} <span className="text-muted-foreground">exec</span>
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Rondas</p>
                            <p className="tabular-nums">
                                {bot.total_rounds} <span className="text-muted-foreground">total</span>
                            </p>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-1.5 shrink-0">
                        {isActive ? (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="ghost" size="sm" className="h-7 text-xs text-destructive hover:text-destructive">
                                        <Square className="mr-1 h-3 w-3" /> Detener
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>¿Detener bot {symbolDisplay}?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Se cancelarán todas las órdenes abiertas y el bot dejará de operar.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => router.post(`/bots/${bot.id}/stop`)}
                                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        >
                                            Detener
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        ) : (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="ghost" size="sm" className="h-7 text-xs">
                                        <Play className="mr-1 h-3 w-3" /> Iniciar
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>¿Iniciar bot {symbolDisplay}?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            El bot comenzará a operar y colocará órdenes en Binance.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                        <AlertDialogAction onClick={() => router.post(`/bots/${bot.id}/start`)}>
                                            Iniciar
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                        <Button variant="ghost" size="sm" className="h-7 text-xs" asChild>
                            <Link href={`/bots/${bot.id}`}>
                                <ArrowUpRight className="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
