import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
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
import { cn } from "@/lib/utils";
import { BinanceAccount } from "@/types/bot";
import { LEVERAGE_OPTIONS, SUPPORTED_PAIRS } from "@/utils/constants";
import { Calculator, Loader2 } from "lucide-react";

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
}

interface BotFormProps {
    data: BotFormData;
    setData: (key: string, value: any) => void;
    errors: Record<string, string>;
    processing: boolean;
    accounts: BinanceAccount[];
    onCalculate: () => void;
}

export default function BotForm({
    data,
    setData,
    errors,
    processing,
    accounts,
    onCalculate,
}: BotFormProps) {
    const sides = [
        { value: "long", label: "Largo", color: "bg-green-500 text-white" },
        { value: "short", label: "Corto", color: "bg-red-500 text-white" },
        { value: "neutral", label: "Neutral", color: "bg-blue-500 text-white" },
    ];

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Configuración básica</CardTitle>
                    <CardDescription>
                        Seleccioná la cuenta y el par a operar
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Cuenta Binance</Label>
                            <Select
                                value={data.binance_account_id}
                                onValueChange={(v) =>
                                    setData("binance_account_id", v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar cuenta" />
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
                            {errors.binance_account_id && (
                                <p className="text-sm text-destructive">
                                    {errors.binance_account_id}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Nombre del Bot</Label>
                            <Input
                                value={data.name}
                                onChange={(e) =>
                                    setData("name", e.target.value)
                                }
                                placeholder="Ej: BTC Grid 1"
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Par de Trading</Label>
                        <Select
                            value={data.symbol}
                            onValueChange={(v) => setData("symbol", v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar par" />
                            </SelectTrigger>
                            <SelectContent>
                                {SUPPORTED_PAIRS.map((pair) => (
                                    <SelectItem key={pair} value={pair}>
                                        {pair.replace("USDT", "/USDT")}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Dirección de estrategia</Label>
                        <div className="grid grid-cols-3 gap-2">
                            {sides.map((s) => (
                                <Button
                                    key={s.value}
                                    type="button"
                                    variant={
                                        data.side === s.value
                                            ? "default"
                                            : "outline"
                                    }
                                    onClick={() => setData("side", s.value)}
                                    className={cn(
                                        data.side === s.value && s.color,
                                        "transition-all",
                                    )}
                                >
                                    {s.label}
                                </Button>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Parámetros del Grid</CardTitle>
                    <CardDescription>
                        Configurá el rango de precios y la cantidad de rejillas
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Precio inferior (USDT)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={data.price_lower}
                                onChange={(e) =>
                                    setData("price_lower", e.target.value)
                                }
                                placeholder="0.00"
                            />
                            {errors.price_lower && (
                                <p className="text-sm text-destructive">
                                    {errors.price_lower}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Precio superior (USDT)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                value={data.price_upper}
                                onChange={(e) =>
                                    setData("price_upper", e.target.value)
                                }
                                placeholder="0.00"
                            />
                            {errors.price_upper && (
                                <p className="text-sm text-destructive">
                                    {errors.price_upper}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Cantidad de rejillas ({data.grid_count})</Label>
                        <Input
                            type="number"
                            min="2"
                            max="200"
                            value={data.grid_count}
                            onChange={(e) =>
                                setData("grid_count", e.target.value)
                            }
                        />
                        {errors.grid_count && (
                            <p className="text-sm text-destructive">
                                {errors.grid_count}
                            </p>
                        )}
                    </div>
                    <Separator />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Inversión (USDT)</Label>
                            <Input
                                type="number"
                                step="1"
                                value={data.investment}
                                onChange={(e) =>
                                    setData("investment", e.target.value)
                                }
                                placeholder="100"
                            />
                            {errors.investment && (
                                <p className="text-sm text-destructive">
                                    {errors.investment}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Apalancamiento</Label>
                            <Select
                                value={String(data.leverage)}
                                onValueChange={(v) => setData("leverage", v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {LEVERAGE_OPTIONS.map((lev) => (
                                        <SelectItem
                                            key={lev}
                                            value={String(lev)}
                                        >
                                            {lev}x
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Deslizamiento (%)</Label>
                        <Input
                            type="number"
                            step="0.01"
                            value={data.slippage}
                            onChange={(e) =>
                                setData("slippage", e.target.value)
                            }
                        />
                    </div>
                </CardContent>
            </Card>

            <Button
                type="button"
                onClick={onCalculate}
                disabled={
                    processing ||
                    !data.price_lower ||
                    !data.price_upper ||
                    !data.name ||
                    !data.binance_account_id
                }
                className="w-full"
                size="lg"
            >
                {processing ? (
                    <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Calculando...
                    </>
                ) : (
                    <>
                        <Calculator className="mr-2 h-4 w-4" />
                        Calcular y ver previa
                    </>
                )}
            </Button>
        </div>
    );
}
