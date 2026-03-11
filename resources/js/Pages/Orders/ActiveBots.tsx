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
import { Separator } from "@/components/ui/separator";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Bot } from "@/types/bot";
import { formatCurrency, sideLabel } from "@/utils/formatters";
import { Head, Link, router } from "@inertiajs/react";
import { ChevronRight, Copy, Play, Square } from "lucide-react";
import { useState } from "react";
import { OrdersLayout } from "./OrdersLayout";

interface ActiveBotsProps {
    bots: Bot[];
}

export default function ActiveBots({ bots }: ActiveBotsProps) {
    const [selectedBot, setSelectedBot] = useState<Bot | null>(
        bots.length > 0 ? bots[0] : null,
    );

    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Ordenes Activas" />
            <OrdersLayout>
                <div className="flex h-full">
                    {/* Bot list */}
                    <div className="flex-1 border-r overflow-auto">
                        <div className="flex items-center justify-between border-b px-5 py-3">
                            <h2 className="text-sm font-semibold">
                                Grid Bot (Ordenes activas)
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                Todos ({bots.length})
                            </span>
                        </div>

                        {bots.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-20 text-center">
                                <p className="text-sm text-muted-foreground">
                                    No hay bots activos
                                </p>
                                <Button asChild size="sm" className="mt-4">
                                    <Link href="/bots">Crear bot</Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {bots.map((bot) => (
                                    <BotCard
                                        key={bot.id}
                                        bot={bot}
                                        isSelected={selectedBot?.id === bot.id}
                                        onSelect={() => setSelectedBot(bot)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Detail panel */}
                    {selectedBot && (
                        <div className="hidden w-[420px] shrink-0 overflow-auto xl:block">
                            <BotDetailPanel bot={selectedBot} />
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}

function BotCard({
    bot,
    isSelected,
    onSelect,
}: {
    bot: Bot;
    isSelected: boolean;
    onSelect: () => void;
}) {
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

    return (
        <div
            onClick={onSelect}
            className={`cursor-pointer px-5 py-4 transition-colors ${isSelected ? "bg-primary/5 border-l-2 border-l-primary" : "hover:bg-muted/30"}`}
        >
            {/* Header */}
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2.5">
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
                        Grid Bot de Futuros
                    </span>
                </div>
                <Badge
                    variant="outline"
                    className="text-[10px] font-semibold"
                >
                    {bot.leverage}x {sideLabel(bot.side)}
                </Badge>
            </div>

            {/* Stats row */}
            <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-xs">
                <div>
                    <span className="text-muted-foreground">
                        Inversión real(USDT)
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
                    <span className="text-muted-foreground">
                        Trend PNL(USDT)
                    </span>
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
                    {bot.rounds_24h ?? 0} rondas 24h ({bot.total_rounds}{" "}
                    total)
                </span>
            </div>

            {/* Order counts */}
            <div className="mt-2 flex items-center gap-3 text-[11px] text-muted-foreground">
                <span>
                    Órdenes: {bot.open_orders_count ?? 0} abiertas /{" "}
                    {bot.filled_orders_count ?? 0} ejecutadas
                </span>
                {bot.started_at && (
                    <>
                        <Separator
                            orientation="vertical"
                            className="h-3"
                        />
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
                    <span className="text-muted-foreground">Inv. total</span>
                    <p className="tabular-nums">
                        ${formatCurrency(bot.investment)}
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
                {bot.status === "active" ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Square className="mr-1 h-3 w-3" /> Detener
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent onClick={(e) => e.stopPropagation()}>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Detener bot {bot.symbol.replace("USDT", "/USDT")}?
                                </AlertDialogTitle>
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
                                    Detener bot
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                ) : (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                size="sm"
                                className="h-7 text-xs"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Play className="mr-1 h-3 w-3" /> Iniciar
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent onClick={(e) => e.stopPropagation()}>
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
                    className="h-7 text-xs"
                    asChild
                >
                    <Link
                        href={`/bots/${bot.id}`}
                        onClick={(e) => e.stopPropagation()}
                    >
                        Detalles <ChevronRight className="ml-1 h-3 w-3" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}

function BotDetailPanel({ bot }: { bot: Bot }) {
    const symbolDisplay = bot.symbol.replace("USDT", "/USDT");
    const pnlPct =
        bot.real_investment > 0
            ? (bot.total_pnl / bot.real_investment) * 100
            : 0;

    const stats = [
        {
            label: "Distribución",
            values: [
                { label: "Inversión real", value: formatCurrency(bot.real_investment) },
                { label: "Margen adicional", value: formatCurrency(bot.additional_margin) },
                { label: "Precio liq.", value: formatCurrency(bot.est_liquidation_price) },
            ],
        },
    ];

    return (
        <div className="h-full border-l">
            {/* Header */}
            <div className="border-b px-4 py-3">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-bold">{symbolDisplay} Grid Bot de Futuros</h3>
                    <Badge variant="outline" className="text-[10px]">
                        {bot.leverage}x {sideLabel(bot.side)}
                    </Badge>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b px-4">
                <div className="flex gap-4 text-xs">
                    {["Resumen", "Transacciones", "Historial"].map((tab, i) => (
                        <button
                            key={tab}
                            className={`py-2 border-b-2 transition-colors ${i === 0 ? "border-primary text-primary font-medium" : "border-transparent text-muted-foreground hover:text-foreground"}`}
                        >
                            {tab}
                        </button>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div className="p-4 space-y-4">
                {/* Distribution */}
                <Card>
                    <CardContent className="pt-4 pb-3">
                        <p className="text-xs text-muted-foreground mb-3">Distribución</p>
                        <div className="grid grid-cols-3 gap-3 text-xs">
                            <div>
                                <p className="text-muted-foreground">Inversión real</p>
                                <p className="font-semibold tabular-nums">{formatCurrency(bot.real_investment)}</p>
                            </div>
                            <div className="text-center">
                                <p className="text-muted-foreground">Margen adic.</p>
                                <p className="font-semibold tabular-nums">{formatCurrency(bot.additional_margin)}</p>
                            </div>
                            <div className="text-right">
                                <p className="text-muted-foreground">Precio liq.</p>
                                <p className="font-semibold tabular-nums">{formatCurrency(bot.est_liquidation_price)}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Ganancia USDT */}
                <Card>
                    <CardContent className="pt-4 pb-3">
                        <p className="text-xs text-muted-foreground mb-2">Ganancia en USDT</p>
                        <div className="flex items-center justify-between">
                            <p className={`text-lg font-bold tabular-nums ${bot.total_pnl >= 0 ? "text-green-500" : "text-red-500"}`}>
                                {bot.total_pnl >= 0 ? "+" : ""}{formatCurrency(bot.total_pnl)} USDT
                            </p>
                            <span className={`text-xs ${bot.total_pnl >= 0 ? "text-green-500" : "text-red-500"}`}>
                                ({pnlPct >= 0 ? "+" : ""}{pnlPct.toFixed(2)}%)
                            </span>
                        </div>
                        <div className="mt-2 text-xs text-muted-foreground">
                            Txs 24H: {bot.rounds_24h} / Txs Totales: {bot.total_rounds}
                        </div>

                        {/* Mini chart placeholder */}
                        <div className="mt-3 h-24 rounded-md bg-muted/30 flex items-center justify-center text-xs text-muted-foreground">
                            PNL Chart
                        </div>
                    </CardContent>
                </Card>

                {/* Bot ID */}
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>ID del Bot</span>
                    <button
                        className="flex items-center gap-1 hover:text-foreground"
                        onClick={() => navigator.clipboard.writeText(String(bot.id))}
                    >
                        #{bot.id}
                        <Copy className="h-3 w-3" />
                    </button>
                </div>
            </div>
        </div>
    );
}
