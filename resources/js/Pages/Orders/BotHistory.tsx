import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Bot } from "@/types/bot";
import { formatCurrency, formatDate, sideLabel, statusLabel } from "@/utils/formatters";
import { Head, Link } from "@inertiajs/react";
import { ChevronRight } from "lucide-react";
import { OrdersLayout } from "./OrdersLayout";

interface BotHistoryProps {
    bots: Bot[];
}

export default function BotHistory({ bots }: BotHistoryProps) {
    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Historial de Bots" />
            <OrdersLayout>
                <div className="p-5">
                    <h2 className="text-sm font-semibold mb-4">
                        Historial de Grid Bots ({bots.length})
                    </h2>

                    {bots.length === 0 ? (
                        <div className="flex flex-col items-center py-16 text-sm text-muted-foreground">
                            No hay bots finalizados aún.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {bots.map((bot) => {
                                const pnlPct =
                                    bot.real_investment > 0
                                        ? (bot.total_pnl / bot.real_investment) * 100
                                        : 0;
                                return (
                                    <div
                                        key={bot.id}
                                        className="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/20"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-semibold">
                                                        {bot.symbol.replace("USDT", "/USDT")}
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            bot.status === "error"
                                                                ? "destructive"
                                                                : "secondary"
                                                        }
                                                        className="text-[10px]"
                                                    >
                                                        {statusLabel(bot.status)}
                                                    </Badge>
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {bot.leverage}x {sideLabel(bot.side)}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    {bot.grid_count} rejillas · {bot.total_rounds} rondas
                                                    · {formatDate(bot.created_at)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-6">
                                            <div className="text-right">
                                                <p className="text-xs text-muted-foreground">
                                                    PNL Final
                                                </p>
                                                <p
                                                    className={`text-sm font-bold tabular-nums ${bot.total_pnl >= 0 ? "text-green-500" : "text-red-500"}`}
                                                >
                                                    {bot.total_pnl >= 0 ? "+" : ""}
                                                    {formatCurrency(bot.total_pnl)} USDT
                                                    <span className="text-xs font-normal ml-1">
                                                        ({pnlPct >= 0 ? "+" : ""}
                                                        {pnlPct.toFixed(2)}%)
                                                    </span>
                                                </p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 text-xs"
                                                asChild
                                            >
                                                <Link href={`/bots/${bot.id}`}>
                                                    Ver <ChevronRight className="ml-1 h-3 w-3" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}
