import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Bot as BotType } from "@/types/bot";
import { formatCurrency } from "@/utils/formatters";
import { Link } from "@inertiajs/react";
import { Plus } from "lucide-react";

interface ActiveBotsListProps {
    bots: BotType[];
}

export default function ActiveBotsList({ bots }: ActiveBotsListProps) {
    if (bots.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm font-medium">
                        Bots Activos
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-col items-center justify-center py-8 text-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 mb-3">
                            <Plus className="h-6 w-6 text-primary" />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            No hay bots activos
                        </p>
                        <Button asChild size="sm" className="mt-4">
                            <Link href="/bots/create">Crear primer bot</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="text-sm font-medium">
                    Bots Activos ({bots.length})
                </CardTitle>
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/bots">Ver todos →</Link>
                </Button>
            </CardHeader>
            <CardContent className="space-y-3">
                {bots.map((bot) => (
                    <Link
                        key={bot.id}
                        href={`/bots/${bot.id}`}
                        className="flex items-center justify-between rounded-lg border p-3 transition-colors hover:bg-accent"
                    >
                        <div className="flex items-center gap-3">
                            <div className="relative flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">
                                {bot.symbol.replace("USDT", "")}
                                <span
                                    className={`absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5 rounded-full ${
                                        bot.status === "active"
                                            ? "bg-green-500"
                                            : "bg-muted-foreground/60"
                                    }`}
                                >
                                    {bot.status === "active" && (
                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                    )}
                                </span>
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">
                                        {bot.name}
                                    </span>
                                    <Badge
                                        variant="default"
                                        className="text-[10px] h-5"
                                    >
                                        {bot.leverage}x
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {bot.grid_count} rejillas ·{" "}
                                    {bot.total_rounds} rondas
                                </p>
                            </div>
                        </div>
                        <div className="text-right">
                            <p
                                className={`text-sm font-semibold ${bot.total_pnl >= 0 ? "text-green-500" : "text-destructive"}`}
                            >
                                {bot.total_pnl >= 0 ? "+" : ""}
                                {formatCurrency(bot.total_pnl)} USDT
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(bot.real_investment)} inv.
                            </p>
                        </div>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
