import { useDarkMode } from "@/hooks/useDarkMode";
import { cn } from "@/lib/utils";
import {
    ColorType,
    CrosshairMode,
    IChartApi,
    IPriceLine,
    ISeriesApi,
    createChart,
} from "lightweight-charts";
import { useCallback, useEffect, useRef, useState } from "react";

export interface ChartOrder {
    id: number;
    side: string;
    status: string;
    price: number;
    quantity: number;
    time: number;
    created_at_fmt?: string;
    filled_at_fmt?: string;
}

export interface BotPreview {
    symbol: string;
    side: string;
    priceLower?: number;
    priceUpper?: number;
    gridCount?: number;
    investment: string;
    leverage: string;
    slippage: string;
    stopLoss?: string;
    takeProfit?: string;
    gridMode: string;
    botMode: string;
}

interface TradingViewChartProps {
    symbol: string;
    lowerPrice?: number;
    upperPrice?: number;
    gridCount?: number;
    side?: string;
    orders?: ChartOrder[];
    botPreview?: BotPreview;
}

const INTERVALS = [
    { label: "1m", value: "1m" },
    { label: "5m", value: "5m" },
    { label: "15m", value: "15m" },
    { label: "1H", value: "1h" },
    { label: "4H", value: "4h" },
    { label: "1D", value: "1d" },
    { label: "1W", value: "1w" },
    { label: "1M", value: "1M" },
] as const;

async function fetchKlines(
    symbol: string,
    interval: string,
    limit = 300,
): Promise<any[]> {
    const res = await fetch(
        `https://api.binance.com/api/v3/klines?symbol=${symbol}&interval=${interval}&limit=${limit}`,
    );
    const data = await res.json();
    return data.map((k: any) => ({
        time: k[0] / 1000,
        open: parseFloat(k[1]),
        high: parseFloat(k[2]),
        low: parseFloat(k[3]),
        close: parseFloat(k[4]),
    }));
}

export default function TradingViewChart({
    symbol,
    lowerPrice: _lowerPrice,
    upperPrice: _upperPrice,
    gridCount: _gridCount,
    side = "long",
    orders = [],
    botPreview,
}: TradingViewChartProps) {
    const lowerPrice = _lowerPrice != null ? Number(_lowerPrice) : undefined;
    const upperPrice = _upperPrice != null ? Number(_upperPrice) : undefined;
    const gridCount = _gridCount != null ? Number(_gridCount) : undefined;
    const containerRef = useRef<HTMLDivElement>(null);
    const chartRef = useRef<IChartApi | null>(null);
    const candleRef = useRef<ISeriesApi<"Candlestick"> | null>(null);
    const gridLinesRef = useRef<IPriceLine[]>([]);
    const orderLinesRef = useRef<IPriceLine[]>([]);
    const upperAreaRef = useRef<ISeriesApi<"Area"> | null>(null);
    const lowerAreaRef = useRef<ISeriesApi<"Area"> | null>(null);
    const candleDataRef = useRef<any[]>([]);

    const { isDark } = useDarkMode();
    const [interval, setInterval] = useState("1d");
    const [loading, setLoading] = useState(false);
    const [showPreview, setShowPreview] = useState(true);

    const buildChart = useCallback(() => {
        if (!containerRef.current) return;

        if (chartRef.current) {
            chartRef.current.remove();
            chartRef.current = null;
            candleRef.current = null;
            upperAreaRef.current = null;
            lowerAreaRef.current = null;
            gridLinesRef.current = [];
        }

        const chart = createChart(containerRef.current, {
            layout: {
                background: {
                    type: ColorType.Solid,
                    color: isDark ? "#09090b" : "#ffffff",
                },
                textColor: isDark ? "#a1a1aa" : "#71717a",
                fontFamily: "Inter, system-ui, sans-serif",
                fontSize: 11,
            },
            grid: {
                vertLines: {
                    color: isDark
                        ? "rgba(255,255,255,0.03)"
                        : "rgba(0,0,0,0.04)",
                },
                horzLines: {
                    color: isDark
                        ? "rgba(255,255,255,0.03)"
                        : "rgba(0,0,0,0.04)",
                },
            },
            crosshair: { mode: CrosshairMode.Normal },
            timeScale: {
                borderColor: isDark ? "#27272a" : "#e4e4e7",
                timeVisible: true,
            },
            rightPriceScale: {
                borderColor: isDark ? "#27272a" : "#e4e4e7",
            },
            width: containerRef.current.clientWidth,
            height: containerRef.current.clientHeight || 400,
        });

        const candleSeries = chart.addCandlestickSeries({
            upColor: "#22c55e",
            downColor: "#ef4444",
            borderVisible: false,
            wickUpColor: "#22c55e",
            wickDownColor: "#ef4444",
        });

        chartRef.current = chart;
        candleRef.current = candleSeries;

        const handleResize = () => {
            if (containerRef.current && chartRef.current) {
                chartRef.current.applyOptions({
                    width: containerRef.current.clientWidth,
                    height: containerRef.current.clientHeight,
                });
            }
        };

        const resizeObserver = new ResizeObserver(handleResize);
        resizeObserver.observe(containerRef.current);

        return () => {
            resizeObserver.disconnect();
            chart.remove();
            chartRef.current = null;
            candleRef.current = null;
        };
    }, [isDark]);

    useEffect(() => {
        const cleanup = buildChart();
        return cleanup;
    }, [buildChart]);

    useEffect(() => {
        if (!candleRef.current) return;
        setLoading(true);
        fetchKlines(symbol, interval)
            .then((data) => {
                candleDataRef.current = data;
                candleRef.current?.setData(data as any);
                chartRef.current?.timeScale().fitContent();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [symbol, interval, isDark]);

    useEffect(() => {
        const series = candleRef.current;
        const chart = chartRef.current;
        if (!series || !chart) return;

        gridLinesRef.current.forEach((line) => {
            try {
                series.removePriceLine(line);
            } catch {}
        });
        gridLinesRef.current = [];

        if (upperAreaRef.current) {
            try {
                chart.removeSeries(upperAreaRef.current);
            } catch {}
            upperAreaRef.current = null;
        }
        if (lowerAreaRef.current) {
            try {
                chart.removeSeries(lowerAreaRef.current);
            } catch {}
            lowerAreaRef.current = null;
        }

        if (!lowerPrice || !upperPrice || lowerPrice >= upperPrice) return;

        // Upper boundary
        const upperLine = series.createPriceLine({
            price: upperPrice,
            color: "#ef4444",
            lineWidth: 2,
            lineStyle: 0,
            axisLabelVisible: true,
            title: "",
        });
        gridLinesRef.current.push(upperLine);

        // Lower boundary
        const lowerLine = series.createPriceLine({
            price: lowerPrice,
            color: "#22c55e",
            lineWidth: 2,
            lineStyle: 0,
            axisLabelVisible: true,
            title: "",
        });
        gridLinesRef.current.push(lowerLine);

        // Internal grid lines
        const count = gridCount || 0;
        if (count > 2) {
            const visibleLines = Math.min(count - 1, 60);
            const step = (upperPrice - lowerPrice) / count;
            const drawStep =
                visibleLines < count - 1
                    ? (upperPrice - lowerPrice) / visibleLines
                    : step;
            const midPoint = (upperPrice + lowerPrice) / 2;

            for (let i = 1; i < visibleLines; i++) {
                const price = lowerPrice + drawStep * i;
                const isSellZone = price > midPoint;

                const line = series.createPriceLine({
                    price,
                    color: isSellZone
                        ? "rgba(239, 68, 68, 0.25)"
                        : "rgba(34, 197, 94, 0.25)",
                    lineWidth: 1,
                    lineStyle: 2,
                    axisLabelVisible: false,
                });
                gridLinesRef.current.push(line);
            }
        }

        // Color bands using actual candle timestamps
        const candles = candleDataRef.current;
        if (candles.length > 0) {
            const firstTime = candles[0].time;
            const lastTime = candles[candles.length - 1].time;
            const timeStep = candles.length > 1 ? candles[1].time - candles[0].time : 60;
            const extendedEnd = lastTime + timeStep * 50;

            const bandTimes: number[] = [];
            for (let t = firstTime; t <= extendedEnd; t += timeStep) {
                bandTimes.push(t);
            }

            const upperArea = chart.addAreaSeries({
                topColor:
                    side === "short"
                        ? "rgba(34, 197, 94, 0.08)"
                        : "rgba(239, 68, 68, 0.08)",
                bottomColor: "rgba(0, 0, 0, 0)",
                lineColor: "transparent",
                lineWidth: 1 as any,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });

            try {
                upperArea.setData(
                    bandTimes.map((t) => ({ time: t as any, value: upperPrice })),
                );
            } catch {}
            upperAreaRef.current = upperArea;

            const lowerArea = chart.addAreaSeries({
                topColor: "rgba(0, 0, 0, 0)",
                bottomColor:
                    side === "short"
                        ? "rgba(239, 68, 68, 0.08)"
                        : "rgba(34, 197, 94, 0.08)",
                lineColor: "transparent",
                lineWidth: 1 as any,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });

            try {
                lowerArea.setData(
                    bandTimes.map((t) => ({ time: t as any, value: lowerPrice })),
                );
            } catch {}
            lowerAreaRef.current = lowerArea;
        }
    }, [lowerPrice, upperPrice, gridCount, side, isDark, interval]);

    useEffect(() => {
        const series = candleRef.current;
        if (!series) return;

        orderLinesRef.current.forEach((line) => {
            try { series.removePriceLine(line); } catch {}
        });
        orderLinesRef.current = [];
        series.setMarkers([]);

        if (orders.length === 0) return;

        const pendingOrders = orders.filter((o) => o.status === "open");
        const filledOrders = orders.filter((o) => o.status === "filled");

        pendingOrders.forEach((o) => {
            const isBuy = o.side === "buy";
            const line = series.createPriceLine({
                price: Number(o.price),
                color: isBuy ? "rgba(34, 197, 94, 0.8)" : "rgba(239, 68, 68, 0.8)",
                lineWidth: 1,
                lineStyle: 2,
                axisLabelVisible: true,
                title: isBuy ? "● C" : "● V",
            });
            orderLinesRef.current.push(line);
        });

        filledOrders
            .sort((a, b) => b.time - a.time)
            .slice(0, 10)
            .forEach((o) => {
                const isBuy = o.side === "buy";
                const line = series.createPriceLine({
                    price: Number(o.price),
                    color: isBuy ? "rgba(34, 197, 94, 0.45)" : "rgba(239, 68, 68, 0.45)",
                    lineWidth: 1,
                    lineStyle: 0,
                    axisLabelVisible: false,
                    title: isBuy ? "✓ C" : "✓ V",
                });
                orderLinesRef.current.push(line);
            });
    }, [orders, isDark, interval]);

    const hasPreviewData = botPreview && (botPreview.priceLower || botPreview.investment !== "0");

    return (
        <div className="h-full flex flex-col">
            <div className="flex items-center px-3 py-1.5 border-b text-xs gap-1 shrink-0 bg-card/30">
                {INTERVALS.map((t) => (
                    <button
                        key={t.value}
                        onClick={() => setInterval(t.value)}
                        className={cn(
                            "px-2 py-1 rounded transition-colors",
                            t.value === interval
                                ? "bg-primary/15 text-primary font-medium"
                                : "text-muted-foreground hover:text-foreground hover:bg-muted/50",
                        )}
                    >
                        {t.label}
                    </button>
                ))}
                {loading && (
                    <span className="ml-2 text-[10px] text-muted-foreground animate-pulse">
                        Cargando...
                    </span>
                )}
                {orders.length > 0 && (
                    <span className="ml-auto text-[10px] text-muted-foreground">
                        {orders.filter((o) => o.status === "open").length}{" "}
                        pendientes ·{" "}
                        {orders.filter((o) => o.status === "filled").length}{" "}
                        ejecutadas
                    </span>
                )}
            </div>

            <div className="flex-1 min-h-0 relative">
                <div className="absolute inset-0" ref={containerRef} />

                {/* Bot preview floating panel */}
                {hasPreviewData && showPreview && botPreview && (
                    <div className="absolute top-2 left-2 z-10 bg-card/90 backdrop-blur-sm border border-border/50 rounded-lg p-2.5 text-[10px] shadow-lg max-w-[200px] select-none">
                        <div className="flex items-center justify-between mb-1.5">
                            <span className="text-xs font-semibold text-foreground">
                                Preview Bot
                            </span>
                            <button
                                onClick={() => setShowPreview(false)}
                                className="text-muted-foreground hover:text-foreground transition-colors ml-2 leading-none"
                            >
                                ✕
                            </button>
                        </div>

                        <div className="space-y-1 text-muted-foreground">
                            <div className="flex justify-between">
                                <span>Par</span>
                                <span className="text-foreground font-medium">
                                    {botPreview.symbol.replace("USDT", "/USDT")}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Modo</span>
                                <span className={cn(
                                    "font-medium px-1 rounded",
                                    botPreview.botMode === "futures"
                                        ? "text-blue-400 bg-blue-500/10"
                                        : "text-emerald-400 bg-emerald-500/10",
                                )}>
                                    {botPreview.botMode === "futures" ? "Futuros" : "Spot"}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Dirección</span>
                                <span className={cn(
                                    "font-medium",
                                    botPreview.side === "long"
                                        ? "text-green-500"
                                        : botPreview.side === "short"
                                          ? "text-red-500"
                                          : "text-foreground",
                                )}>
                                    {botPreview.side === "long" ? "Long" : botPreview.side === "short" ? "Short" : "Neutral"}
                                </span>
                            </div>
                            {botPreview.priceLower != null && botPreview.priceUpper != null && (
                                <div className="flex justify-between">
                                    <span>Rango</span>
                                    <span className="text-foreground tabular-nums">
                                        {botPreview.priceLower.toLocaleString()} - {botPreview.priceUpper.toLocaleString()}
                                    </span>
                                </div>
                            )}
                            {botPreview.gridCount != null && (
                                <div className="flex justify-between">
                                    <span>Rejillas</span>
                                    <span className="text-foreground tabular-nums">{botPreview.gridCount}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span>Inversión</span>
                                <span className="text-foreground tabular-nums">{botPreview.investment} USDT</span>
                            </div>
                            {botPreview.botMode === "futures" && Number(botPreview.leverage) > 1 && (
                                <div className="flex justify-between">
                                    <span>Leverage</span>
                                    <span className="text-foreground tabular-nums">{botPreview.leverage}x</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span>Grid</span>
                                <span className="text-foreground">
                                    {botPreview.gridMode === "geometric" ? "Geométrica" : "Aritmética"}
                                </span>
                            </div>
                            {botPreview.stopLoss && (
                                <div className="flex justify-between">
                                    <span>SL</span>
                                    <span className="text-red-400 tabular-nums">{botPreview.stopLoss}</span>
                                </div>
                            )}
                            {botPreview.takeProfit && (
                                <div className="flex justify-between">
                                    <span>TP</span>
                                    <span className="text-green-400 tabular-nums">{botPreview.takeProfit}</span>
                                </div>
                            )}
                            {botPreview.slippage && (
                                <div className="flex justify-between">
                                    <span>Slippage</span>
                                    <span className="text-foreground tabular-nums">{botPreview.slippage}%</span>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {!showPreview && hasPreviewData && (
                    <button
                        onClick={() => setShowPreview(true)}
                        className="absolute top-2 left-2 z-10 bg-card/80 backdrop-blur-sm border border-border/50 rounded-md px-2 py-1 text-[10px] text-muted-foreground hover:text-foreground transition-colors shadow-sm"
                    >
                        Preview ▸
                    </button>
                )}
            </div>
        </div>
    );
}
