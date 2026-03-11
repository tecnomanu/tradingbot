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
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Bot as BotType } from "@/types/bot";
import { formatCurrency, sideLabel, statusLabel } from "@/utils/formatters";
import { Link, router } from "@inertiajs/react";
import { Play, Square } from "lucide-react";

interface BotCardProps {
    bot: BotType;
}

export default function BotCard({ bot }: BotCardProps) {
    const handleStart = () => {
        router.post(`/bots/${bot.id}/start`);
    };
    const handleStop = () => {
        router.post(`/bots/${bot.id}/stop`);
    };

    return (
        <Card className="animate-fade-in transition-shadow hover:shadow-md">
            <Link href={`/bots/${bot.id}`}>
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-sm font-bold text-primary">
                                {bot.symbol.replace("USDT", "").substring(0, 3)}
                            </div>
                            <div>
                                <CardTitle className="text-base">
                                    {bot.name}
                                </CardTitle>
                                <CardDescription>
                                    {bot.symbol} · Grid Bot de Futuros
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    bot.status === "active"
                                        ? "default"
                                        : bot.status === "error"
                                          ? "destructive"
                                          : "secondary"
                                }
                            >
                                {bot.status === "active" && (
                                    <span className="mr-1 h-1.5 w-1.5 rounded-full bg-white animate-pulse inline-block" />
                                )}
                                {statusLabel(bot.status)}
                            </Badge>
                            <Badge variant="outline">
                                {bot.leverage}x {sideLabel(bot.side)}
                            </Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="pb-3">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Inversión real
                            </p>
                            <p className="text-lg font-bold">
                                {formatCurrency(bot.real_investment)} USDT
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">
                                Beneficio total
                            </p>
                            <p
                                className={`text-lg font-bold ${bot.total_pnl >= 0 ? "text-green-500" : "text-destructive"}`}
                            >
                                {bot.total_pnl >= 0 ? "+" : ""}
                                {formatCurrency(bot.total_pnl)} USDT
                            </p>
                        </div>
                    </div>
                    <Separator className="my-3" />
                    <div className="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Grid profit
                            </p>
                            <p className="text-sm font-semibold text-primary">
                                {formatCurrency(bot.grid_profit)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Rango
                            </p>
                            <p className="text-sm font-semibold">
                                {formatCurrency(bot.price_lower, 0)}-
                                {formatCurrency(bot.price_upper, 0)}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Rejillas / Rondas
                            </p>
                            <p className="text-sm font-semibold">
                                {bot.grid_count} / {bot.total_rounds}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Link>
            <CardFooter className="pt-0">
                {bot.status === "active" ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="destructive" size="sm">
                                <Square className="mr-1 h-3 w-3" /> Detener
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
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
                                    onClick={handleStop}
                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                >
                                    Detener bot
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                ) : bot.status === "pending" || bot.status === "stopped" ? (
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="sm">
                                <Play className="mr-1 h-3 w-3" /> Iniciar
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
                                <AlertDialogAction onClick={handleStart}>
                                    Iniciar bot
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                ) : null}
            </CardFooter>
        </Card>
    );
}
