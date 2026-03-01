import { cn } from "@/lib/utils";
import { useEffect, useRef, useState } from "react";

interface OrderBookProps {
    symbol: string;
    currentPrice: number | null;
}

interface BookLevel {
    price: number;
    qty: number;
    total: number;
}

export default function OrderBook({ symbol, currentPrice }: OrderBookProps) {
    const [asks, setAsks] = useState<BookLevel[]>([]);
    const [bids, setBids] = useState<BookLevel[]>([]);
    const wsRef = useRef<WebSocket | null>(null);
    const DEPTH = 14;

    useEffect(() => {
        const stream = symbol.toLowerCase();
        const ws = new WebSocket(
            `wss://stream.binance.com:9443/ws/${stream}@depth20@100ms`,
        );
        wsRef.current = ws;

        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);

            const rawAsks: [string, string][] = data.asks || [];
            const rawBids: [string, string][] = data.bids || [];

            const parseLevel = (
                levels: [string, string][],
            ): BookLevel[] => {
                let cumTotal = 0;
                return levels.slice(0, DEPTH).map(([p, q]) => {
                    const price = parseFloat(p);
                    const qty = parseFloat(q);
                    cumTotal += qty;
                    return { price, qty, total: cumTotal };
                });
            };

            setAsks(parseLevel(rawAsks).reverse());
            setBids(parseLevel(rawBids));
        };

        return () => {
            ws.close();
            wsRef.current = null;
        };
    }, [symbol]);

    const maxTotal = Math.max(
        asks[0]?.total || 0,
        bids[bids.length - 1]?.total || 0,
        1,
    );

    const priceDec = symbol.includes("BTC") ? 1 : symbol.includes("ETH") ? 2 : 4;
    const qtyDec = symbol.includes("BTC") ? 5 : symbol.includes("ETH") ? 4 : 2;
    const baseCoin = symbol.replace("USDT", "");

    return (
        <div className="flex flex-col h-full bg-card/30 text-foreground text-xs">
            <div className="flex items-center justify-between px-3 py-2 border-b">
                <span className="text-xs font-semibold">Libro</span>
            </div>

            <div className="grid grid-cols-3 gap-0 px-3 py-1.5 text-[10px] text-muted-foreground border-b">
                <span>Precio(USDT)</span>
                <span className="text-right">Cant({baseCoin})</span>
                <span className="text-right">Total({baseCoin})</span>
            </div>

            <div className="flex-1 overflow-hidden flex flex-col">
                {/* Asks */}
                <div className="flex-1 flex flex-col justify-end px-1 min-h-0 overflow-hidden">
                    {asks.map((level, i) => {
                        const pct = (level.total / maxTotal) * 100;
                        return (
                            <div
                                key={`a-${i}`}
                                className="relative grid grid-cols-3 gap-0 px-2 py-[1px] hover:bg-muted/30 cursor-pointer"
                            >
                                <div
                                    className="absolute right-0 top-0 bottom-0 bg-red-500/10"
                                    style={{ width: `${pct}%` }}
                                />
                                <span className="relative text-red-500 tabular-nums">
                                    {level.price.toFixed(priceDec)}
                                </span>
                                <span className="relative text-right tabular-nums">
                                    {level.qty.toFixed(qtyDec)}
                                </span>
                                <span className="relative text-right tabular-nums text-muted-foreground">
                                    {level.total.toFixed(qtyDec)}
                                </span>
                            </div>
                        );
                    })}
                </div>

                {/* Spread / Price */}
                <div className="flex items-center justify-between px-3 py-2 border-y bg-card/50">
                    <span
                        className={cn(
                            "text-base font-semibold tabular-nums",
                            currentPrice && currentPrice > 0
                                ? "text-green-500"
                                : "text-muted-foreground",
                        )}
                    >
                        {currentPrice
                            ? currentPrice.toLocaleString("en-US", {
                                  minimumFractionDigits: 2,
                              })
                            : "--"}
                    </span>
                    <span className="text-[10px] text-muted-foreground tabular-nums">
                        ≈ ${currentPrice?.toLocaleString("en-US", { minimumFractionDigits: 2 }) || "--"}
                    </span>
                </div>

                {/* Bids */}
                <div className="flex-1 flex flex-col justify-start px-1 min-h-0 overflow-hidden">
                    {bids.map((level, i) => {
                        const pct = (level.total / maxTotal) * 100;
                        return (
                            <div
                                key={`b-${i}`}
                                className="relative grid grid-cols-3 gap-0 px-2 py-[1px] hover:bg-muted/30 cursor-pointer"
                            >
                                <div
                                    className="absolute right-0 top-0 bottom-0 bg-green-500/10"
                                    style={{ width: `${pct}%` }}
                                />
                                <span className="relative text-green-500 tabular-nums">
                                    {level.price.toFixed(priceDec)}
                                </span>
                                <span className="relative text-right tabular-nums">
                                    {level.qty.toFixed(qtyDec)}
                                </span>
                                <span className="relative text-right tabular-nums text-muted-foreground">
                                    {level.total.toFixed(qtyDec)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Buy/Sell pressure bar */}
            <div className="px-3 py-2 border-t">
                <BuySellBar
                    buyTotal={bids[bids.length - 1]?.total || 0}
                    sellTotal={asks[0]?.total || 0}
                />
            </div>
        </div>
    );
}

function BuySellBar({
    buyTotal,
    sellTotal,
}: {
    buyTotal: number;
    sellTotal: number;
}) {
    const total = buyTotal + sellTotal || 1;
    const buyPct = ((buyTotal / total) * 100).toFixed(0);
    const sellPct = ((sellTotal / total) * 100).toFixed(0);

    return (
        <div className="space-y-1">
            <div className="flex h-1.5 rounded-full overflow-hidden">
                <div
                    className="bg-green-500 transition-all duration-500"
                    style={{ width: `${buyPct}%` }}
                />
                <div
                    className="bg-red-500 transition-all duration-500"
                    style={{ width: `${sellPct}%` }}
                />
            </div>
            <div className="flex justify-between text-[10px] tabular-nums">
                <span className="text-green-500">B {buyPct}%</span>
                <span className="text-red-500">{sellPct}% S</span>
            </div>
        </div>
    );
}
