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
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Bot } from "@/types/bot";
import { Link, router } from "@inertiajs/react";
import { Bot as BotIcon, Pencil } from "lucide-react";
import { useState } from "react";

interface BotTableProps {
    bots: Bot[];
}

type TabFilter = "all" | "futures" | "spot" | "active" | "stopped";

const TABS: { key: TabFilter; label: string }[] = [
    { key: "all", label: "Todos" },
    { key: "futures", label: "Futuros" },
    { key: "spot", label: "Spot" },
    { key: "active", label: "Activos" },
    { key: "stopped", label: "Detenidos" },
];

function filterBots(bots: Bot[], tab: TabFilter): Bot[] {
    switch (tab) {
        case "futures":
            return bots.filter((b) => Number(b.leverage) > 1);
        case "spot":
            return bots.filter((b) => Number(b.leverage) <= 1);
        case "active":
            return bots.filter((b) => b.status === "active");
        case "stopped":
            return bots.filter((b) => b.status === "stopped");
        default:
            return bots;
    }
}

export default function BotTable({ bots }: BotTableProps) {
    const [activeTab, setActiveTab] = useState<TabFilter>("all");

    const filteredBots = filterBots(bots, activeTab);

    const counts: Record<TabFilter, number> = {
        all: bots.length,
        futures: bots.filter((b) => Number(b.leverage) > 1).length,
        spot: bots.filter((b) => Number(b.leverage) <= 1).length,
        active: bots.filter((b) => b.status === "active").length,
        stopped: bots.filter((b) => b.status === "stopped").length,
    };

    return (
        <div className="h-full flex flex-col text-foreground">
            <div className="flex border-b text-xs px-2 py-1 gap-1 bg-card/30 shrink-0 overflow-x-auto">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={cn(
                            "px-3 py-1.5 rounded-md transition-colors whitespace-nowrap",
                            activeTab === tab.key
                                ? "bg-primary/10 text-primary font-medium"
                                : "text-muted-foreground hover:text-foreground hover:bg-muted/50",
                        )}
                    >
                        {tab.label} ({counts[tab.key]})
                    </button>
                ))}
            </div>

            <div className="flex-1 overflow-auto">
                {filteredBots.length === 0 ? (
                    <div className="h-full flex flex-col items-center justify-center text-center opacity-60">
                        <BotIcon className="h-8 w-8 text-muted-foreground mb-3" />
                        <p className="text-sm">Sin bots operando</p>
                    </div>
                ) : (
                    <div className="min-w-[900px]">
                        <div className="grid grid-cols-[1.3fr_0.8fr_0.8fr_0.8fr_1fr_0.7fr_0.7fr_1.2fr] text-[10px] text-muted-foreground border-b px-4 py-2 bg-muted/5 font-medium">
                            <div>Bot</div>
                            <div>Par</div>
                            <div className="text-right">Inversión real</div>
                            <div className="text-right">Ganancia</div>
                            <div className="text-right">
                                Último/Precio est. liq.
                            </div>
                            <div className="text-center">Órdenes</div>
                            <div className="text-right">Margen adic.</div>
                            <div className="text-center">Operación</div>
                        </div>

                        {filteredBots.map((bot) => (
                            <BotRow key={bot.id} bot={bot} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function BotRow({ bot }: { bot: Bot }) {
    const pnl = parseFloat(bot.total_pnl as any);
    const investment = parseFloat(bot.investment as any);
    const pnlPct = investment > 0 ? (pnl / investment) * 100 : 0;
    const [stopping, setStopping] = useState(false);
    const isFutures = Number(bot.leverage) > 1;

    const handleStop = () => {
        setStopping(true);
        router.post(
            `/bots/${bot.id}/stop`,
            {},
            { onFinish: () => setStopping(false) },
        );
    };

    return (
        <div className="grid grid-cols-[1.3fr_0.8fr_0.8fr_0.8fr_1fr_0.7fr_0.7fr_1.2fr] items-center text-xs border-b px-4 py-3 hover:bg-muted/20 transition-colors">
            <div className="flex items-center gap-2">
                <div
                    className={cn(
                        "w-1.5 h-1.5 rounded-full",
                        bot.status === "active"
                            ? "bg-green-500 pulse-active"
                            : bot.status === "pending"
                              ? "bg-yellow-500"
                              : bot.status === "error"
                                ? "bg-red-500"
                                : "bg-muted-foreground",
                    )}
                />
                <span className="font-medium">
                    {bot.symbol.replace("USDT", "/USDT")} Grid Bot
                </span>
            </div>

            <div>
                <div className="font-medium">
                    {bot.symbol.replace("USDT", "/USDT")}
                </div>
                <div className="flex items-center gap-1 mt-0.5">
                    <span
                        className={cn(
                            "text-[10px] px-1 py-0.5 rounded",
                            bot.side === "long"
                                ? "bg-green-500/15 text-green-500"
                                : "bg-red-500/15 text-red-500",
                        )}
                    >
                        {bot.side === "long" ? "Largo" : "Corto"}
                    </span>
                    {isFutures && (
                        <span className="text-[10px] text-muted-foreground">
                            {bot.leverage}x
                        </span>
                    )}
                    <span className={cn(
                        "text-[10px] px-1 py-0.5 rounded",
                        isFutures
                            ? "bg-blue-500/15 text-blue-500"
                            : "bg-emerald-500/15 text-emerald-500",
                    )}>
                        {isFutures ? "Futuros" : "Spot"}
                    </span>
                </div>
            </div>

            <div className="text-right tabular-nums">
                {investment.toFixed(1)} USDT
            </div>

            <div className="text-right">
                <span
                    className={cn(
                        "font-medium tabular-nums",
                        pnl >= 0 ? "text-green-500" : "text-red-500",
                    )}
                >
                    {pnl > 0 ? "+" : ""}
                    {pnl.toFixed(2)} USDT
                </span>
                <div
                    className={cn(
                        "text-[10px] tabular-nums",
                        pnl >= 0 ? "text-green-500" : "text-red-500",
                    )}
                >
                    ({pnlPct.toFixed(2)}%)
                </div>
            </div>

            <div className="text-right tabular-nums text-muted-foreground">
                {parseFloat(
                    (bot.est_liquidation_price as any) || 0,
                ).toLocaleString("en-US", { minimumFractionDigits: 1 })}{" "}
                USDT
            </div>

            {/* Order counts */}
            <div className="text-center">
                <div className="flex items-center justify-center gap-1.5">
                    <span className="tabular-nums text-yellow-500" title="Pendientes">
                        {bot.open_orders_count ?? 0}
                    </span>
                    <span className="text-muted-foreground">/</span>
                    <span className="tabular-nums text-green-500" title="Ejecutadas">
                        {bot.filled_orders_count ?? 0}
                    </span>
                </div>
                <div className="text-[9px] text-muted-foreground">
                    pend / ejec
                </div>
            </div>

            <div className="text-right tabular-nums text-muted-foreground">
                {parseFloat((bot.additional_margin as any) || 0).toFixed(1)}{" "}
                USDT
            </div>

            <div className="flex justify-center gap-2">
                {bot.status === "active" ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-[10px] px-3"
                                disabled={stopping}
                            >
                                {stopping ? "Deteniendo..." : "Detener"}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Detener bot{" "}
                                    {bot.symbol.replace("USDT", "/USDT")}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Se cancelarán todas las órdenes abiertas en
                                    Binance y el bot dejará de operar.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={handleStop}
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
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-[10px] px-3"
                            >
                                Iniciar
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Iniciar bot {bot.symbol.replace("USDT", "/USDT")}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    El bot comenzará a operar y colocará órdenes en Binance.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => router.post(`/bots/${bot.id}/start`)}
                                >
                                    Iniciar bot
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 text-[10px] px-3"
                    asChild
                >
                    <Link href={`/bots/${bot.id}`}>Detalles</Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 text-[10px] px-2"
                    asChild
                    title="Editar"
                >
                    <Link href={`/bots/${bot.id}/edit`}>
                        <Pencil className="h-3 w-3" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}
