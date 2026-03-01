import {
    ColorType,
    createChart,
    IChartApi,
    ISeriesApi,
} from "lightweight-charts";
import { useTheme } from "next-themes";
import { useEffect, useRef } from "react";

interface CryptoChartProps {
    symbol: string;
    lowerPrice?: number;
    upperPrice?: number;
    gridCount?: number;
}

// Generates fake OHLC data for the selected symbol around a base price
function generateData(basePrice: number) {
    const data = [];
    let currentPrice = basePrice;
    const now = new Date();
    now.setHours(0, 0, 0, 0);

    for (let i = 100; i >= 0; i--) {
        const time = new Date(now);
        time.setDate(time.getDate() - i);

        const volatility = basePrice * 0.02;
        const open = currentPrice;
        const close = open + (Math.random() - 0.5) * volatility;
        const high = Math.max(open, close) + Math.random() * (volatility / 2);
        const low = Math.min(open, close) - Math.random() * (volatility / 2);

        data.push({
            time: time.getTime() / 1000,
            open,
            high,
            low,
            close,
        });

        currentPrice = close;
    }
    return data;
}

export default function CryptoChart({
    symbol,
    lowerPrice,
    upperPrice,
    gridCount,
}: CryptoChartProps) {
    const chartContainerRef = useRef<HTMLDivElement>(null);
    const chartRef = useRef<IChartApi | null>(null);
    const seriesRef = useRef<ISeriesApi<"Candlestick"> | null>(null);
    const { theme } = useTheme();
    const isDark = theme === "dark" || theme === "system";

    useEffect(() => {
        if (!chartContainerRef.current) return;

        const handleResize = () => {
            if (chartContainerRef.current && chartRef.current) {
                chartRef.current.applyOptions({
                    width: chartContainerRef.current.clientWidth,
                    height: chartContainerRef.current.clientHeight,
                });
            }
        };

        const chart = createChart(chartContainerRef.current, {
            layout: {
                background: { type: ColorType.Solid, color: "transparent" },
                textColor: isDark ? "#A3A3A3" : "#525252",
            },
            grid: {
                vertLines: { color: isDark ? "#262626" : "#e5e5e5" },
                horzLines: { color: isDark ? "#262626" : "#e5e5e5" },
            },
            timeScale: {
                borderColor: isDark ? "#262626" : "#e5e5e5",
            },
            rightPriceScale: {
                borderColor: isDark ? "#262626" : "#e5e5e5",
            },
            width: chartContainerRef.current.clientWidth,
            height: chartContainerRef.current.clientHeight || 400,
        });

        const candlestickSeries = chart.addCandlestickSeries({
            upColor: "#22c55e",
            downColor: "#ef4444",
            borderVisible: false,
            wickUpColor: "#22c55e",
            wickDownColor: "#ef4444",
        });

        // Simulate different base prices per symbol
        const basePrice = symbol.includes("BTC")
            ? 65000
            : symbol.includes("ETH")
              ? 3500
              : 100;
        const data = generateData(basePrice);
        candlestickSeries.setData(data as any);

        chartRef.current = chart;
        seriesRef.current = candlestickSeries;

        window.addEventListener("resize", handleResize);
        // small timeout to ensure right size after render/resiable changes
        setTimeout(handleResize, 100);

        return () => {
            window.removeEventListener("resize", handleResize);
            chart.remove();
        };
    }, [theme, symbol]);

    // Update Grid Lines when inputs change
    useEffect(() => {
        if (!seriesRef.current || !lowerPrice || !upperPrice) return;
        const series = seriesRef.current;

        // Clear old lines. API v4 requires storing created lines and removing them, but
        // a quick trick to reset is just rebuilding the grid array.
        // Since lightweight-charts doesn't have `removeAllPriceLines()`, we'll just not plot 500 lines to avoid slowness,
        // we'll plot max 50 for visual representation

        // In a real reactive app with `lightweight-charts`, we'd keep an array of `IPriceLine` refs and remove them.
        // For this prototype, we'll draw upper/lower boundaries always, and a few internal grids if requested.

        const upperLine = series.createPriceLine({
            price: upperPrice,
            color: "#ef4444",
            lineWidth: 2,
            lineStyle: 0,
            axisLabelVisible: true,
            title: "Upper",
        });

        const lowerLine = series.createPriceLine({
            price: lowerPrice,
            color: "#22c55e",
            lineWidth: 2,
            lineStyle: 0,
            axisLabelVisible: true,
            title: "Lower",
        });

        const internalLines: any[] = [];

        if (gridCount && gridCount > 2) {
            const step = (upperPrice - lowerPrice) / gridCount;
            // Limit visual lines to prevent performance issues (e.g. if gridCount is 130, draw 20)
            const visibleLines = Math.min(gridCount, 40);
            const displayStep = (upperPrice - lowerPrice) / visibleLines;

            for (let i = 1; i < visibleLines; i++) {
                const price = lowerPrice + displayStep * i;
                const line = series.createPriceLine({
                    price: price,
                    color: isDark
                        ? "rgba(255, 255, 255, 0.1)"
                        : "rgba(0, 0, 0, 0.1)",
                    lineWidth: 1,
                    lineStyle: 2, // Dashed
                    axisLabelVisible: false,
                });
                internalLines.push(line);
            }
        }

        return () => {
            // Cleanup lines when props change so they don't multiply
            series.removePriceLine(upperLine);
            series.removePriceLine(lowerLine);
            internalLines.forEach((l) => series.removePriceLine(l));
        };
    }, [lowerPrice, upperPrice, gridCount, isDark]);

    return <div className="w-full h-full relative" ref={chartContainerRef} />;
}
