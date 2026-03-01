import { cn } from "@/lib/utils";
import { useEffect, useRef, useState } from "react";

function StatCell({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-[10px] text-muted-foreground">{label}</span>
            <span
                className={cn("font-medium tabular-nums text-foreground", className)}
            >
                {value}
            </span>
        </div>
    );
}

interface TickerData {
    price: number;
    priceChange: number;
    priceChangePercent: number;
    high24h: number;
    low24h: number;
    volume24h: number;
    prevPrice: number;
}

interface TickerBarProps {
    symbol: string;
    onPriceUpdate?: (price: number) => void;
}

function formatVolume(v: number): string {
    if (v >= 1_000_000_000) return (v / 1_000_000_000).toFixed(2) + " B";
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(2) + " M";
    if (v >= 1_000) return (v / 1_000).toFixed(2) + " K";
    return v.toFixed(2);
}

export default function TickerBar({ symbol, onPriceUpdate }: TickerBarProps) {
    const [ticker, setTicker] = useState<TickerData | null>(null);
    const wsRef = useRef<WebSocket | null>(null);

    useEffect(() => {
        const stream = symbol.toLowerCase();
        const ws = new WebSocket(
            `wss://stream.binance.com:9443/ws/${stream}@ticker`,
        );
        wsRef.current = ws;

        ws.onmessage = (event) => {
            const d = JSON.parse(event.data);
            const price = parseFloat(d.c);
            const prev = parseFloat(d.o);
            setTicker({
                price,
                priceChange: parseFloat(d.p),
                priceChangePercent: parseFloat(d.P),
                high24h: parseFloat(d.h),
                low24h: parseFloat(d.l),
                volume24h: parseFloat(d.q),
                prevPrice: prev,
            });
            onPriceUpdate?.(price);
        };

        return () => {
            ws.close();
            wsRef.current = null;
        };
    }, [symbol]);

    const baseCoin = symbol.replace("USDT", "");
    const symbolDisplay = `${baseCoin}/USDT`;
    const isUp = ticker ? ticker.priceChangePercent >= 0 : true;

    return (
        <div className="flex items-center gap-5 px-4 py-2 border-b bg-card/50 text-foreground shrink-0 overflow-x-auto whitespace-nowrap">
            <div className="flex items-center gap-2.5 shrink-0">
                <img
                    src={`https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/32/color/${baseCoin.toLowerCase()}.png`}
                    alt={baseCoin}
                    className="w-6 h-6 rounded-full"
                    onError={(e) => {
                        (e.target as HTMLImageElement).style.display = "none";
                    }}
                />
                <div className="flex items-center gap-1.5">
                    <span className="text-sm font-bold tracking-tight">
                        {symbolDisplay}
                    </span>
                    <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                        Perp
                    </span>
                </div>
            </div>

            <div className="flex flex-col leading-tight shrink-0">
                <span
                    className={cn(
                        "text-lg font-bold tabular-nums",
                        ticker
                            ? isUp
                                ? "text-green-500"
                                : "text-red-500"
                            : "text-muted-foreground",
                    )}
                >
                    {ticker
                        ? ticker.price.toLocaleString("en-US", {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                          })
                        : "---"}
                </span>
                <span className="text-[10px] text-muted-foreground tabular-nums">
                    ≈ $
                    {ticker
                        ? ticker.price.toLocaleString("en-US", {
                              minimumFractionDigits: 2,
                              maximumFractionDigits: 2,
                          })
                        : "---"}
                </span>
            </div>

            <div className="h-8 w-px bg-border shrink-0" />

            <div className="flex items-center gap-5 text-xs shrink-0">
                <StatCell
                    label="Cambio 24H"
                    value={
                        ticker
                            ? `${isUp ? "+" : ""}${ticker.priceChangePercent.toFixed(2)}%`
                            : "--"
                    }
                    className={isUp ? "text-green-500" : "text-red-500"}
                />
                <StatCell
                    label="Max 24H"
                    value={
                        ticker
                            ? ticker.high24h.toLocaleString("en-US", {
                                  minimumFractionDigits: 2,
                              })
                            : "--"
                    }
                />
                <StatCell
                    label="Min 24H"
                    value={
                        ticker
                            ? ticker.low24h.toLocaleString("en-US", {
                                  minimumFractionDigits: 2,
                              })
                            : "--"
                    }
                />
                <StatCell
                    label="Vol 24H(USDT)"
                    value={ticker ? formatVolume(ticker.volume24h) : "--"}
                />
            </div>
        </div>
    );
}
