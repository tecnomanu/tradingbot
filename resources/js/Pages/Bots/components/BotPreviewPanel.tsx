import { cn } from "@/lib/utils";
import { useState } from "react";

export interface BotPreviewData {
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
    status?: string;
    totalPnl?: number;
    gridProfit?: number;
    pendingOrders?: number;
    filledOrders?: number;
}

interface BotPreviewPanelProps {
    data: BotPreviewData;
    className?: string;
}

export default function BotPreviewPanel({ data, className }: BotPreviewPanelProps) {
    const [open, setOpen] = useState(true);
    const isLive = !!data.status;

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="absolute top-2 left-2 z-10 bg-card/80 backdrop-blur-sm border border-border/50 rounded-md px-2 py-1 text-[10px] text-muted-foreground hover:text-foreground transition-colors shadow-sm"
            >
                Preview ▸
            </button>
        );
    }

    return (
        <div className={cn(
            "absolute top-2 left-2 z-10 bg-card/90 backdrop-blur-sm border border-border/50 rounded-lg p-3 text-[10px] shadow-lg min-w-[220px] select-none",
            className,
        )}>
            <div className="flex items-center justify-between mb-1.5">
                <span className="text-xs font-semibold text-foreground">
                    {isLive ? "Bot Info" : "Preview Bot"}
                </span>
                <button
                    onClick={() => setOpen(false)}
                    className="text-muted-foreground hover:text-foreground transition-colors ml-2 leading-none"
                >
                    ✕
                </button>
            </div>

            <div className="space-y-1 text-muted-foreground">
                <Row label="Par" value={data.symbol.replace("USDT", "/USDT")} className="text-foreground font-medium" />

                <Row label="Modo">
                    <span className={cn(
                        "font-medium px-1 rounded",
                        data.botMode === "futures"
                            ? "text-blue-400 bg-blue-500/10"
                            : "text-emerald-400 bg-emerald-500/10",
                    )}>
                        {data.botMode === "futures" ? "Futures" : "Spot"}
                    </span>
                </Row>

                <Row label="Dirección">
                    <span className={cn(
                        "font-medium",
                        data.side === "long" ? "text-green-500"
                            : data.side === "short" ? "text-red-500"
                            : "text-foreground",
                    )}>
                        {data.side === "long" ? "Long" : data.side === "short" ? "Short" : "Neutral"}
                    </span>
                </Row>

                {isLive && data.status && (
                    <Row label="Estado">
                        <span className={cn(
                            "font-medium",
                            data.status === "active" ? "text-green-500"
                                : data.status === "error" ? "text-red-500"
                                : "text-muted-foreground",
                        )}>
                            {data.status === "active" ? "Activo"
                                : data.status === "stopped" ? "Detenido"
                                : data.status === "error" ? "Error"
                                : data.status}
                        </span>
                    </Row>
                )}

                {data.priceLower != null && data.priceUpper != null && (
                    <Row label="Rango" value={`${data.priceLower.toLocaleString()} – ${data.priceUpper.toLocaleString()}`} className="text-foreground tabular-nums" />
                )}

                {data.gridCount != null && (
                    <Row label="Rejillas" value={String(data.gridCount)} className="text-foreground tabular-nums" />
                )}

                <Row label="Inversión" value={`${data.investment} USDT`} className="text-foreground tabular-nums" />

                {data.botMode === "futures" && Number(data.leverage) > 1 && (
                    <Row label="Leverage" value={`${data.leverage}x`} className="text-foreground tabular-nums" />
                )}

                <Row label="Grid">
                    <span className="text-foreground">
                        {data.gridMode === "geometric" ? "Geométrica" : "Aritmética"}
                    </span>
                </Row>

                {data.stopLoss && (
                    <Row label="SL" value={data.stopLoss} className="text-red-400 tabular-nums" />
                )}

                {data.takeProfit && (
                    <Row label="TP" value={data.takeProfit} className="text-green-400 tabular-nums" />
                )}

                {data.slippage && (
                    <Row label="Slippage" value={`${data.slippage}%`} className="text-foreground tabular-nums" />
                )}

                {isLive && data.totalPnl != null && (
                    <>
                        <div className="border-t border-border/40 my-1" />
                        <Row label="PNL Total">
                            <span className={cn(
                                "font-medium tabular-nums",
                                Number(data.totalPnl) >= 0 ? "text-green-500" : "text-red-500",
                            )}>
                                {Number(data.totalPnl) >= 0 ? "+" : ""}{Number(data.totalPnl).toFixed(2)}
                            </span>
                        </Row>
                    </>
                )}

                {isLive && data.gridProfit != null && (
                    <Row label="Grid Profit">
                        <span className="text-primary font-medium tabular-nums">
                            +{Number(data.gridProfit).toFixed(2)}
                        </span>
                    </Row>
                )}

                {isLive && (data.pendingOrders != null || data.filledOrders != null) && (
                    <Row label="Órdenes">
                        <span className="text-foreground tabular-nums">
                            <span className="text-yellow-500">{data.pendingOrders ?? 0}</span>
                            {" / "}
                            <span className="text-green-500">{data.filledOrders ?? 0}</span>
                        </span>
                    </Row>
                )}
            </div>
        </div>
    );
}

function Row({ label, value, className, children }: {
    label: string;
    value?: string;
    className?: string;
    children?: React.ReactNode;
}) {
    return (
        <div className="flex justify-between gap-3">
            <span>{label}</span>
            {children ?? <span className={className}>{value}</span>}
        </div>
    );
}
