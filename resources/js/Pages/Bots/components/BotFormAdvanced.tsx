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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { BinanceAccount } from "@/types/bot";
import { sideColor, sideLabel } from "@/utils/botBadges";
import { LEVERAGE_OPTIONS } from "@/utils/constants";
import { Link } from "@inertiajs/react";
import axios from "axios";
import { ChevronDown, Info, Loader2 } from "lucide-react";
import { useEffect, useState } from "react";
import PairSelector from "./PairSelector";

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
    grid_mode: string;
    drawdown_mode: string;
    soft_guard_drawdown_pct: string;
    hard_guard_drawdown_pct: string;
    hard_guard_action: string;
    reentry_enabled: boolean;
    reentry_cooldown_minutes: string;
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
    editBotId?: number;
    showGridLines?: boolean;
    onShowGridLinesChange?: (v: boolean) => void;
    botMode?: "futures" | "spot";
    onPriceFetched?: (price: number) => void;
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
    editBotId,
    showGridLines = true,
    onShowGridLinesChange,
    botMode = "futures",
    onPriceFetched,
}: BotFormAdvancedProps) {
    const [showLeverageModal, setShowLeverageModal] = useState(false);
    const [tempLeverage, setTempLeverage] = useState<number>(
        parseInt(data.leverage) || 3,
    );
    const [showAdvanced, setShowAdvanced] = useState(false);

    useEffect(() => {
        if (!isEditing) {
            setData("name", `${data.symbol.replace("USDT", "")} Grid Bot`);
        }
    }, [data.symbol, isEditing]);

    const isFutures = botMode === "futures";
    const investment = parseFloat(data.investment) || 0;
    const isInvestmentValid =
        balance !== null && investment <= balance && investment > 0;

    const lev = isFutures ? (parseInt(data.leverage) || 1) : 1;
    const realInvestment = investment / lev;
    const marginAdicional = investment - realInvestment;
    const leveragedInvestment = realInvestment * lev;

    const MAINTENANCE_MARGIN_RATE = 0.004;
    const liqPrice = currentPrice && lev > 1
        ? data.side === "short"
            ? (currentPrice * (1 + 1 / lev - MAINTENANCE_MARGIN_RATE)).toFixed(1)
            : (currentPrice * (1 - 1 / lev + MAINTENANCE_MARGIN_RATE)).toFixed(1)
        : "---";

    const sliderPct =
        balance && balance > 0
            ? Math.min(Math.round((investment / balance) * 100), 100)
            : 0;

    const priceLower = parseFloat(data.price_lower) || 0;
    const priceUpper = parseFloat(data.price_upper) || 0;
    const gridCountNum = parseInt(data.grid_count, 10);
    const gridCountInvalid = !isNaN(gridCountNum) && (gridCountNum < 2 || gridCountNum > 500);
    const priceRangeInvalid = priceUpper > 0 && priceLower > 0 && priceUpper <= priceLower;

    const canCalculate =
        !processing &&
        !!data.price_lower &&
        !!data.price_upper &&
        !!data.binance_account_id &&
        isInvestmentValid &&
        !priceRangeInvalid &&
        !gridCountInvalid;

    const calculateRecommendedGrids = () => {
        if (priceLower <= 0 || priceUpper <= priceLower) return;
        const midPrice = (priceUpper + priceLower) / 2;
        const recommended = Math.round((priceUpper - priceLower) / (midPrice * 0.003));
        setData("grid_count", String(Math.max(5, Math.min(50, recommended))));
    };

    const leverageIndex = LEVERAGE_OPTIONS.indexOf(
        tempLeverage as (typeof LEVERAGE_OPTIONS)[number],
    );
    const sliderIdx = leverageIndex >= 0 ? leverageIndex : 0;

    const handleLeveragePrev = () => {
        const idx = LEVERAGE_OPTIONS.indexOf(tempLeverage as (typeof LEVERAGE_OPTIONS)[number]);
        if (idx > 0) setTempLeverage(LEVERAGE_OPTIONS[idx - 1]);
    };
    const handleLeverageNext = () => {
        const idx = LEVERAGE_OPTIONS.indexOf(tempLeverage as (typeof LEVERAGE_OPTIONS)[number]);
        if (idx < LEVERAGE_OPTIONS.length - 1) setTempLeverage(LEVERAGE_OPTIONS[idx + 1]);
    };

    const gridMode = (data as any).grid_mode || "arithmetic";

    return (
        <div className="flex flex-col h-full text-foreground">
            <div className="p-4 space-y-5 overflow-y-auto flex-1 custom-scrollbar">
                <div className="space-y-3">
                    <div className="space-y-1.5">
                        <Label className="text-[11px] text-muted-foreground">
                            Elija un par comercial
                        </Label>
                        <PairSelector
                            value={data.symbol}
                            onValueChange={(v) => setData("symbol", v)}
                            isFutures={isFutures}
                            align="start"
                        >
                            <button
                                type="button"
                                className="flex items-center justify-between w-full h-9 px-3 text-xs border rounded-md bg-background hover:bg-muted/40 transition-colors"
                            >
                                <div className="flex items-center gap-2">
                                    <img
                                        src={`https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${data.symbol.replace("USDT", "").toLowerCase()}.png`}
                                        alt={data.symbol}
                                        className="w-4 h-4 rounded-full"
                                        onError={(e) => { (e.target as HTMLImageElement).style.display = "none"; }}
                                    />
                                    <span className="font-medium">
                                        {data.symbol.replace("USDT", "/USDT")}{isFutures ? " Futuros" : ""}
                                    </span>
                                </div>
                                <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
                            </button>
                        </PairSelector>
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

                    {/* Side selector */}
                    <div className="space-y-1.5">
                        <div className="flex bg-muted/50 p-0.5 rounded-lg">
                            {(["long", "short", "neutral"] as const).map(
                                (side) => {
                                    const colors = sideColor(side);
                                    return (
                                        <button
                                            key={side}
                                            onClick={() =>
                                                setData("side", side)
                                            }
                                            className={cn(
                                                "flex-1 text-xs py-1.5 rounded-md transition-all font-medium",
                                                data.side === side
                                                    ? cn(colors.bg, colors.text, "shadow-sm ring-1 ring-current/20")
                                                    : "text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            {sideLabel(side)}
                                        </button>
                                    );
                                },
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
                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                onClick={async () => {
                                    try {
                                        const res = await axios.post<{ data?: { price?: number }; price?: number }>("/bots/current-price", { symbol: data.symbol });
                                        const price = res.data?.data?.price ?? res.data?.price;
                                        if (typeof price === "number" && price > 0) {
                                            const lower = Math.round(price * 0.95);
                                            const upper = Math.round(price * 1.05);
                                            setData("price_lower", lower.toString());
                                            setData("price_upper", upper.toString());
                                            onPriceFetched?.(price);
                                        }
                                    } catch {
                                        if (currentPrice && currentPrice > 0) {
                                            const lower = Math.round(currentPrice * 0.95);
                                            const upper = Math.round(currentPrice * 1.05);
                                            setData("price_lower", lower.toString());
                                            setData("price_upper", upper.toString());
                                        }
                                    }
                                }}
                                className="text-[10px] text-primary hover:underline"
                            >
                                Obtener precio actual
                            </button>
                            {currentPrice && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        const lower = Math.round(currentPrice * 0.95);
                                        const upper = Math.round(currentPrice * 1.05);
                                        setData("price_lower", lower.toString());
                                        setData("price_upper", upper.toString());
                                    }}
                                    className="text-[10px] text-primary hover:underline"
                                >
                                    ±5% auto
                                </button>
                            )}
                        </div>
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
                    {priceLower > 0 && priceUpper > 0 && priceUpper <= priceLower && (
                        <p className="text-[10px] text-destructive font-medium">
                            El precio superior debe ser mayor al inferior
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
                        <button
                            type="button"
                            onClick={calculateRecommendedGrids}
                            disabled={priceLower <= 0 || priceUpper <= priceLower}
                            className={cn(
                                "text-[10px] bg-muted hover:bg-muted/80 px-2.5 py-2 rounded transition-colors shrink-0 h-9",
                                (priceLower <= 0 || priceUpper <= priceLower) && "opacity-50 cursor-not-allowed",
                            )}
                        >
                            Recomendado
                        </button>
                    </div>
                    {(errors.grid_count || gridCountInvalid) && (
                        <p className="text-[10px] text-destructive">
                            {errors.grid_count || "La cantidad de rejillas debe estar entre 2 y 500"}
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
                                className="h-3.5 w-3.5 border-orange-500 data-[state=checked]:bg-orange-500 rounded-[3px]"
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
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <div className="flex items-center space-x-1 text-[10px] text-muted-foreground">
                                        <Info className="h-3 w-3" />
                                        <span>Reserva automática</span>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent side="left" className="max-w-[200px] text-xs">
                                    El margen se reserva automáticamente en Binance al iniciar el bot.
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
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
                        {isFutures && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-9 px-3 text-xs font-medium"
                                onClick={() => setShowLeverageModal(true)}
                            >
                                {data.leverage}x
                            </Button>
                        )}
                    </div>
                    {errors.investment && (
                        <p className="text-[10px] text-destructive">
                            {errors.investment}
                        </p>
                    )}

                    {isFutures && (
                        <p className="text-[10px] text-muted-foreground tabular-nums">
                            Inversión real ({realInvestment.toFixed(1)}) + Margen
                            adicional ({marginAdicional.toFixed(1)}) USDT
                        </p>
                    )}

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
                        {isFutures && (
                            <>
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
                            </>
                        )}
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
                                    <button
                                        type="button"
                                        onClick={() => setData("grid_mode", "arithmetic")}
                                        className={cn(
                                            "px-2 py-0.5 rounded transition-colors",
                                            gridMode === "arithmetic"
                                                ? "bg-background shadow-sm font-medium"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        Aritmética
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setData("grid_mode", "geometric")}
                                        className={cn(
                                            "px-2 py-0.5 rounded transition-colors",
                                            gridMode === "geometric"
                                                ? "bg-background shadow-sm font-medium"
                                                : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        Geométrica
                                    </button>
                                </div>
                            </div>

                            {/* Risk Guard v2 */}
                            <Separator className="my-2" />
                            <div className="space-y-2">
                                <span className="text-xs font-medium text-muted-foreground">Risk Guard</span>

                                <div className="flex justify-between items-center text-xs">
                                    <span className="text-muted-foreground">Modo drawdown</span>
                                    <div className="flex bg-muted/50 p-0.5 rounded text-[10px]">
                                        <button type="button" onClick={() => setData("drawdown_mode", "peak_equity_drawdown")}
                                            className={cn("px-2 py-0.5 rounded transition-colors", data.drawdown_mode === "peak_equity_drawdown" ? "bg-background shadow-sm font-medium" : "text-muted-foreground hover:text-foreground")}>
                                            Desde pico
                                        </button>
                                        <button type="button" onClick={() => setData("drawdown_mode", "initial_capital_loss")}
                                            className={cn("px-2 py-0.5 rounded transition-colors", data.drawdown_mode === "initial_capital_loss" ? "bg-background shadow-sm font-medium" : "text-muted-foreground hover:text-foreground")}>
                                            S/ capital
                                        </button>
                                    </div>
                                </div>
                                <p className="text-[10px] text-muted-foreground/70">
                                    {data.drawdown_mode === "initial_capital_loss"
                                        ? "Pérdida como % de la inversión real."
                                        : "Caída desde el mejor PNL alcanzado."}
                                </p>

                                <div className="flex justify-between items-center gap-3 text-xs">
                                    <span className="text-muted-foreground whitespace-nowrap">Soft Guard %</span>
                                    <Input type="number" step="0.1" value={data.soft_guard_drawdown_pct}
                                        onChange={(e) => setData("soft_guard_drawdown_pct", e.target.value)}
                                        placeholder="15" className="h-7 w-20 text-xs tabular-nums text-right" />
                                </div>
                                <div className="flex justify-between items-center gap-3 text-xs">
                                    <span className="text-muted-foreground whitespace-nowrap">Hard Guard %</span>
                                    <Input type="number" step="0.1" value={data.hard_guard_drawdown_pct}
                                        onChange={(e) => setData("hard_guard_drawdown_pct", e.target.value)}
                                        placeholder="20" className="h-7 w-20 text-xs tabular-nums text-right" />
                                </div>

                                <div className="flex justify-between items-center text-xs">
                                    <span className="text-muted-foreground">Acción hard</span>
                                    <select value={data.hard_guard_action}
                                        onChange={(e) => setData("hard_guard_action", e.target.value)}
                                        className="h-7 text-[10px] bg-background border rounded px-1.5">
                                        <option value="stop_bot_only">Detener</option>
                                        <option value="close_position_and_stop">Cerrar + detener</option>
                                        <option value="pause_and_rebuild">Pausar + rebuild</option>
                                        <option value="notify_only">Solo notificar</option>
                                    </select>
                                </div>

                                <label className="flex items-center gap-2 text-xs cursor-pointer">
                                    <input type="checkbox" checked={data.reentry_enabled}
                                        onChange={(e) => setData("reentry_enabled", e.target.checked)}
                                        className="rounded border-border h-3.5 w-3.5" />
                                    <span className="text-muted-foreground">Re-entry automático</span>
                                </label>

                                {data.reentry_enabled && (
                                    <div className="flex justify-between items-center gap-3 text-xs">
                                        <span className="text-muted-foreground whitespace-nowrap">Cooldown (min)</span>
                                        <Input type="number" value={data.reentry_cooldown_minutes}
                                            onChange={(e) => setData("reentry_cooldown_minutes", e.target.value)}
                                            placeholder="60" className="h-7 w-20 text-xs tabular-nums text-right" />
                                    </div>
                                )}
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
                {!canCalculate && !processing && (
                    <p className="text-[11px] text-muted-foreground text-center">
                        {!data.binance_account_id
                            ? "Seleccioná una cuenta Binance para continuar."
                            : !data.price_lower || !data.price_upper
                              ? "Completá el rango de precios para continuar."
                              : priceRangeInvalid
                                ? "El precio superior debe ser mayor al precio inferior."
                                : "Completá todos los campos para continuar."}
                    </p>
                )}
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
                                onClick={handleLeveragePrev}
                                disabled={sliderIdx <= 0}
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
                                onClick={handleLeverageNext}
                                disabled={sliderIdx >= LEVERAGE_OPTIONS.length - 1}
                            >
                                +
                            </Button>
                        </div>

                        <div className="space-y-4 px-2">
                            <Slider
                                value={[sliderIdx]}
                                onValueChange={(v) =>
                                    setTempLeverage(LEVERAGE_OPTIONS[v[0]])
                                }
                                max={LEVERAGE_OPTIONS.length - 1}
                                min={0}
                                step={1}
                            />
                            <div className="flex justify-between text-[10px] text-muted-foreground tabular-nums">
                                {LEVERAGE_OPTIONS.map((lev) => (
                                    <span
                                        key={lev}
                                        className={cn(
                                            "cursor-pointer hover:text-foreground transition-colors",
                                            tempLeverage === lev && "text-primary font-medium",
                                        )}
                                        onClick={() => setTempLeverage(lev)}
                                    >
                                        {lev}x
                                    </span>
                                ))}
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
