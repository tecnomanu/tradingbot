import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Slider } from "@/components/ui/slider";
import { cn } from "@/lib/utils";
import { BinanceAccount } from "@/types/bot";
import { SUPPORTED_PAIRS } from "@/utils/constants";
import { Link } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { useEffect, useState } from "react";

interface BotFormData {
    binance_account_id: string;
    name: string;
    symbol: string;
    side: string;
    price_lower: string;
    price_upper: string;
    grid_count: string;
    investment: string;
    leverage: string;
    slippage: string;
    stop_loss_price: string;
    take_profit_price: string;
}

interface BotFormAdvancedProps {
    data: BotFormData;
    setData: (key: string, value: any) => void;
    errors: Record<string, string>;
    processing: boolean;
    accounts: BinanceAccount[];
    onCalculate: () => void;
    currentPrice: number | null;
    balance: number | null;
    fetchingBalance: boolean;
    isEditing?: boolean;
    editBotStatus?: string;
    editBotId?: number;
    showGridLines?: boolean;
    onShowGridLinesChange?: (v: boolean) => void;
}

export default function BotFormAdvanced({
    data,
    setData,
    errors,
    processing,
    accounts,
    onCalculate,
    currentPrice,
    balance,
    fetchingBalance,
    isEditing = false,
    editBotStatus,
    editBotId,
    showGridLines = true,
    onShowGridLinesChange,
}: BotFormAdvancedProps) {
    const [showLeverageModal, setShowLeverageModal] = useState(false);
    const [tempLeverage, setTempLeverage] = useState<number>(
        parseInt(data.leverage) || 3,
    );
    const [autoReserve, setAutoReserve] = useState(true);
    const [showAdvanced, setShowAdvanced] = useState(false);

    useEffect(() => {
        setData("name", `${data.symbol.replace("USDT", "")} Grid Bot`);
    }, [data.symbol]);

    const investment = parseFloat(data.investment) || 0;
    const isInvestmentValid =
        balance !== null && investment <= balance && investment > 0;
    const editDisabled = isEditing && editBotStatus === "active";
    const canCalculate =
        !processing &&
        !editDisabled &&
        !!data.price_lower &&
        !!data.price_upper &&
        !!data.binance_account_id &&
        isInvestmentValid;

    const lev = parseInt(data.leverage) || 1;
    const realInvestment = investment * 0.76;
    const marginAdicional = investment * 0.24;
    const leveragedInvestment = investment * lev * 0.76;
    const liqPrice = currentPrice ? (currentPrice * 0.52).toFixed(1) : "---";
    const sliderPct =
        balance && balance > 0
            ? Math.min(Math.round((investment / balance) * 100), 100)
            : 0;

    return (
        <div className="flex flex-col h-full text-foreground">
            <div className="p-4 space-y-5 overflow-y-auto flex-1 custom-scrollbar">
                {/* ─── Strategy toggle ─── */}
                <div className="space-y-3">
                    <div className="space-y-1.5">
                        <Label className="text-[11px] text-muted-foreground">
                            Elija un par comercial
                        </Label>
                        <Select
                            value={data.symbol}
                            onValueChange={(v) => setData("symbol", v)}
                        >
                            <SelectTrigger className="h-9 text-xs">
                                <SelectValue placeholder="Seleccionar par" />
                            </SelectTrigger>
                            <SelectContent>
                                {SUPPORTED_PAIRS.map((pair) => (
                                    <SelectItem key={pair} value={pair}>
                                        {pair.replace("USDT", "/USDT Perp")}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.symbol && (
                            <p className="text-[10px] text-destructive">
                                {errors.symbol}
                            </p>
                        )}
                    </div>

                    {/* Hidden account selector for backend */}
                    <div className="hidden">
                        <Select
                            value={data.binance_account_id}
                            onValueChange={(v) =>
                                setData("binance_account_id", v)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {accounts.map((acc) => (
                                    <SelectItem
                                        key={acc.id}
                                        value={String(acc.id)}
                                    >
                                        {acc.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {errors.binance_account_id && (
                        <p className="text-[10px] text-destructive">
                            Configure una cuenta primero.
                        </p>
                    )}

                    <div className="flex bg-muted/50 p-0.5 rounded-lg">
                        <button className="flex-1 text-xs py-1.5 text-muted-foreground hover:text-foreground rounded transition-colors">
                            Copiar estrategia
                        </button>
                        <button className="flex-1 text-xs py-1.5 bg-background shadow-sm rounded-md font-medium">
                            Configuración manual
                        </button>
                    </div>

                    {/* Side selector */}
                    <div className="space-y-1.5">
                        <div className="flex bg-muted/50 p-0.5 rounded-lg">
                            {(["long", "short", "neutral"] as const).map(
                                (side) => (
                                    <button
                                        key={side}
                                        onClick={() =>
                                            setData("side", side)
                                        }
                                        className={cn(
                                            "flex-1 text-xs py-1.5 rounded-md transition-all font-medium",
                                            data.side === side
                                                ? side === "long"
                                                    ? "bg-green-500 text-white shadow-sm"
                                                    : side === "short"
                                                      ? "bg-red-500 text-white shadow-sm"
                                                      : "bg-background text-foreground shadow-sm"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        {side === "long"
                                            ? "Long"
                                            : side === "short"
                                              ? "Short"
                                              : "Neutral"}
                                    </button>
                                ),
                            )}
                        </div>
                    </div>
                </div>

                <Separator />

                {/* ─── 1. Price Range ─── */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label className="text-xs font-medium">
                            1. Rango de precios
                        </Label>
                        {currentPrice && (
                            <button
                                type="button"
                                onClick={() => {
                                    const lower = Math.round(
                                        currentPrice * 0.95,
                                    );
                                    const upper = Math.round(
                                        currentPrice * 1.05,
                                    );
                                    setData("price_lower", lower.toString());
                                    setData("price_upper", upper.toString());
                                }}
                                className="text-[10px] text-primary hover:underline"
                            >
                                ±5% auto
                            </button>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <span className="text-[10px] text-muted-foreground">
                                Inferior
                            </span>
                            <Input
                                type="number"
                                step="0.01"
                                value={data.price_lower}
                                onChange={(e) =>
                                    setData("price_lower", e.target.value)
                                }
                                placeholder={
                                    currentPrice
                                        ? Math.round(
                                              currentPrice * 0.95,
                                          ).toString()
                                        : "0"
                                }
                                className="h-8 text-xs tabular-nums"
                            />
                        </div>
                        <div className="space-y-1">
                            <span className="text-[10px] text-muted-foreground">
                                Superior
                            </span>
                            <Input
                                type="number"
                                step="0.01"
                                value={data.price_upper}
                                onChange={(e) =>
                                    setData("price_upper", e.target.value)
                                }
                                placeholder={
                                    currentPrice
                                        ? Math.round(
                                              currentPrice * 1.05,
                                          ).toString()
                                        : "0"
                                }
                                className="h-8 text-xs tabular-nums"
                            />
                        </div>
                    </div>
                    {currentPrice && (
                        <p className="text-[10px] text-muted-foreground tabular-nums">
                            Precio actual: {currentPrice.toLocaleString("en-US", { minimumFractionDigits: 2 })} USDT
                        </p>
                    )}
                </div>

                <Separator />

                {/* ─── 2. Grid Count ─── */}
                <div className="space-y-2">
                    <div className="flex justify-between items-center">
                        <Label className="text-xs font-medium">
                            2. Cantidad de rejillas{" "}
                            <span className="text-muted-foreground font-normal text-[10px]">
                                (2-500)
                            </span>
                        </Label>
                    </div>
                    <div className="flex gap-2 items-center">
                        <Input
                            type="number"
                            min="2"
                            max="500"
                            value={data.grid_count}
                            onChange={(e) =>
                                setData("grid_count", e.target.value)
                            }
                            className="h-9 text-xs tabular-nums flex-1"
                        />
                        <button className="text-[10px] bg-muted hover:bg-muted/80 px-2.5 py-2 rounded transition-colors shrink-0 h-9">
                            Recomendado
                        </button>
                    </div>
                    {errors.grid_count && (
                        <p className="text-[10px] text-destructive">
                            {errors.grid_count}
                        </p>
                    )}
                    <div className="flex items-center justify-between">
                        <p className="text-[10px] text-muted-foreground tabular-nums">
                            Ganancia/rejilla (comisión deducida): 0.17%~0.26%
                        </p>
                        <div className="flex items-center space-x-1.5">
                            <Checkbox
                                id="verRejillas"
                                checked={showGridLines}
                                onCheckedChange={(c) =>
                                    onShowGridLinesChange?.(c as boolean)
                                }
                                className="h-3 w-3 border-orange-500 data-[state=checked]:bg-orange-500 rounded-[2px]"
                            />
                            <label
                                htmlFor="verRejillas"
                                className="text-[10px] text-orange-500 font-medium cursor-pointer"
                            >
                                Ver rejillas
                            </label>
                        </div>
                    </div>
                </div>

                <Separator />

                {/* ─── 3. Investment ─── */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Label className="text-xs font-medium">
                            3. Inversión
                        </Label>
                        <div className="flex items-center space-x-1.5">
                            <Checkbox
                                id="reservar"
                                checked={autoReserve}
                                onCheckedChange={(c) =>
                                    setAutoReserve(c as boolean)
                                }
                                className="h-3 w-3 rounded-[2px]"
                            />
                            <label
                                htmlFor="reservar"
                                className="text-[10px] text-muted-foreground"
                            >
                                Reservar automáticamente
                            </label>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <div className="flex-1 relative">
                            <Input
                                type="number"
                                step="1"
                                value={data.investment}
                                onChange={(e) =>
                                    setData("investment", e.target.value)
                                }
                                className="h-9 text-xs font-semibold tabular-nums pr-14"
                                placeholder="0"
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
                                USDT
                            </span>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-9 px-3 text-xs font-medium"
                            onClick={() => setShowLeverageModal(true)}
                        >
                            {data.leverage}x
                        </Button>
                    </div>
                    {errors.investment && (
                        <p className="text-[10px] text-destructive">
                            {errors.investment}
                        </p>
                    )}

                    <p className="text-[10px] text-muted-foreground tabular-nums">
                        Inversión real ({realInvestment.toFixed(1)}) + Margen
                        adicional ({marginAdicional.toFixed(1)}) USDT
                    </p>

                    {/* Balance slider */}
                    <div className="px-1">
                        <Slider
                            value={[sliderPct]}
                            onValueChange={(v) => {
                                if (!balance || balance <= 0) return;
                                const newInvestment = Math.round(
                                    (v[0] / 100) * balance,
                                );
                                setData(
                                    "investment",
                                    Math.max(0, newInvestment).toString(),
                                );
                            }}
                            max={100}
                            min={0}
                            step={1}
                            disabled={!balance || balance <= 0}
                            className={cn(
                                "[&_[role=slider]]:h-3.5 [&_[role=slider]]:w-3.5",
                                !balance || balance <= 0
                                    ? "opacity-40 cursor-not-allowed"
                                    : "",
                            )}
                        />
                        <div className="flex justify-between mt-1.5 px-0.5">
                            {[0, 25, 50, 75, 100].map((p) => (
                                <button
                                    key={p}
                                    type="button"
                                    disabled={!balance || balance <= 0}
                                    onClick={() => {
                                        if (!balance || balance <= 0) return;
                                        const val = Math.round(
                                            (p / 100) * balance,
                                        );
                                        setData("investment", val.toString());
                                    }}
                                    className={cn(
                                        "text-[9px] tabular-nums transition-colors",
                                        !balance || balance <= 0
                                            ? "text-muted-foreground/30 cursor-not-allowed"
                                            : Math.abs(sliderPct - p) < 3
                                              ? "text-primary font-medium"
                                              : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    {p}%
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Balance info */}
                    <div className="space-y-1.5 text-xs">
                        <div className="flex justify-between items-center">
                            <span className="text-muted-foreground">
                                Disponible:
                            </span>
                            <span className="tabular-nums">
                                {fetchingBalance ? (
                                    <Loader2 className="h-3 w-3 animate-spin inline" />
                                ) : (
                                    <span
                                        className={cn(
                                            balance !== null &&
                                                investment > balance
                                                ? "text-red-500"
                                                : "",
                                        )}
                                    >
                                        {balance !== null
                                            ? `${balance.toLocaleString("en-US", { minimumFractionDigits: 0 })} USDT`
                                            : "--"}
                                    </span>
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between items-center">
                            <span className="text-muted-foreground">
                                Inv. real (apalancada):
                            </span>
                            <span className="tabular-nums">
                                {leveragedInvestment.toFixed(1)} USDT
                            </span>
                        </div>
                        <div className="flex justify-between items-center">
                            <span className="text-muted-foreground">
                                Precio est. liquidación:
                            </span>
                            <span className="tabular-nums">
                                {liqPrice} USDT
                            </span>
                        </div>
                    </div>

                    {/* Advanced settings toggle */}
                    <button
                        onClick={() => setShowAdvanced(!showAdvanced)}
                        className="text-xs font-medium hover:underline"
                    >
                        Ajustes avanzados{" "}
                        <span className="text-primary">
                            {showAdvanced ? "Cerrar ▲" : "Abrir ▼"}
                        </span>
                    </button>

                    {showAdvanced && (
                        <div className="space-y-3 pt-2 border-t">
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-muted-foreground">
                                    Control de deslizamiento
                                </span>
                                <span className="tabular-nums">
                                    {data.slippage}% (Rec. IA)
                                </span>
                            </div>
                            <div className="flex justify-between items-center gap-3 text-xs">
                                <span className="text-muted-foreground whitespace-nowrap">
                                    Take Profit
                                </span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.take_profit_price}
                                    onChange={(e) =>
                                        setData("take_profit_price", e.target.value)
                                    }
                                    placeholder={
                                        currentPrice
                                            ? Math.round(currentPrice * 1.10).toString()
                                            : "Precio TP"
                                    }
                                    className="h-7 w-28 text-xs tabular-nums text-right"
                                />
                            </div>
                            <div className="flex justify-between items-center gap-3 text-xs">
                                <span className="text-muted-foreground whitespace-nowrap">
                                    Stop Loss
                                </span>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.stop_loss_price}
                                    onChange={(e) =>
                                        setData("stop_loss_price", e.target.value)
                                    }
                                    placeholder={
                                        currentPrice
                                            ? Math.round(currentPrice * 0.90).toString()
                                            : "Precio SL"
                                    }
                                    className="h-7 w-28 text-xs tabular-nums text-right"
                                />
                            </div>
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-muted-foreground">
                                    Modo de rejilla
                                </span>
                                <div className="flex bg-muted/50 p-0.5 rounded text-[10px]">
                                    <span className="px-2 py-0.5 bg-background rounded shadow-sm font-medium">
                                        Geométrica
                                    </span>
                                    <span className="px-2 py-0.5 text-muted-foreground">
                                        Aritmética
                                    </span>
                                </div>
                            </div>
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-muted-foreground">
                                    Trailing
                                </span>
                                <span className="text-muted-foreground">
                                    Sin establecer
                                </span>
                            </div>
                        </div>
                    )}

                    {balance !== null && investment > balance && (
                        <Alert className="border-red-500/50 bg-red-500/10 py-2 px-3">
                            <AlertDescription className="text-xs text-red-500 font-medium">
                                Saldo insuficiente
                            </AlertDescription>
                        </Alert>
                    )}
                </div>
            </div>

            {/* CTA Button */}
            <div className="p-4 border-t mt-auto space-y-2">
                <Button
                    type="button"
                    onClick={onCalculate}
                    disabled={!canCalculate}
                    className={cn(
                        "w-full h-11 text-sm font-semibold transition-all",
                        canCalculate
                            ? "bg-[#ff5a00] hover:bg-[#e65100] text-white"
                            : "bg-muted text-muted-foreground",
                    )}
                >
                    {processing ? (
                        <>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Calculando...
                        </>
                    ) : editDisabled ? (
                        "Bot activo — detenelo para editar"
                    ) : isEditing ? (
                        "Guardar cambios"
                    ) : (
                        "Crear"
                    )}
                </Button>
                {isEditing && editBotId && (
                    <Button
                        variant="ghost"
                        className="w-full h-9 text-xs text-muted-foreground"
                        asChild
                    >
                        <Link href="/bots">
                            Cancelar y volver
                        </Link>
                    </Button>
                )}
            </div>

            {/* Leverage Modal */}
            <Dialog
                open={showLeverageModal}
                onOpenChange={setShowLeverageModal}
            >
                <DialogContent className="sm:max-w-md bg-card border-border">
                    <DialogHeader>
                        <DialogTitle>Apalancamiento</DialogTitle>
                    </DialogHeader>
                    <div className="py-6 space-y-8">
                        <div className="flex justify-center items-center gap-4">
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                onClick={() =>
                                    setTempLeverage(
                                        Math.max(1, tempLeverage - 1),
                                    )
                                }
                            >
                                -
                            </Button>
                            <div className="text-3xl font-semibold w-20 text-center tabular-nums">
                                {tempLeverage}
                                <span className="text-lg text-muted-foreground ml-1">
                                    x
                                </span>
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-8 w-8"
                                onClick={() =>
                                    setTempLeverage(
                                        Math.min(125, tempLeverage + 1),
                                    )
                                }
                            >
                                +
                            </Button>
                        </div>

                        <div className="space-y-4 px-2">
                            <Slider
                                value={[tempLeverage]}
                                onValueChange={(v) => setTempLeverage(v[0])}
                                max={100}
                                min={1}
                                step={1}
                            />
                            <div className="flex justify-between text-[10px] text-muted-foreground tabular-nums">
                                <span>1x</span>
                                <span>25x</span>
                                <span>50x</span>
                                <span>75x</span>
                                <span>100x</span>
                            </div>
                        </div>

                        <div className="text-muted-foreground text-xs space-y-2 px-2">
                            <p>
                                Para evitar la liquidación, gestione el
                                apalancamiento con precaución.
                            </p>
                            <p>
                                Nocional máximo:{" "}
                                <span className="text-foreground tabular-nums">
                                    {(800000000 / tempLeverage).toLocaleString(
                                        "en-US",
                                    )}{" "}
                                    USDT
                                </span>
                            </p>
                        </div>
                    </div>
                    <DialogFooter className="flex gap-3 sm:justify-center border-t pt-4">
                        <Button
                            variant="outline"
                            onClick={() => setShowLeverageModal(false)}
                            className="w-[120px]"
                        >
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => {
                                setData("leverage", tempLeverage.toString());
                                setShowLeverageModal(false);
                            }}
                            className="w-[120px] bg-[#ff5a00] hover:bg-[#e65100] text-white"
                        >
                            Aceptar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
