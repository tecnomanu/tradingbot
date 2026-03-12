import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { BotPnlSnapshot } from "@/types/bot";
import { formatCurrency } from "@/utils/formatters";
import { BarChart3 } from "lucide-react";
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

interface PnlChartProps {
    data: BotPnlSnapshot[];
}

export default function PnlChart({ data }: PnlChartProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium">
                    Ganancia en USDT
                </CardTitle>
            </CardHeader>
            <CardContent>
                {data.length === 0 ? (
                    <div className="flex h-64 flex-col items-center justify-center text-center text-muted-foreground">
                        <BarChart3 className="h-10 w-10 opacity-30 mb-2" />
                        <p className="text-sm">No hay datos de PNL aún</p>
                        <p className="text-xs opacity-60 mt-1">
                            Los datos aparecerán cuando el bot esté activo
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart
                                    data={data}
                                    margin={{
                                        top: 5,
                                        right: 10,
                                        left: 0,
                                        bottom: 0,
                                    }}
                                >
                                    <defs>
                                        <linearGradient
                                            id="colorPnl"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="#22a962"
                                                stopOpacity={0.3}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="#22a962"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                        <linearGradient
                                            id="colorGrid"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="5%"
                                                stopColor="#3b82f6"
                                                stopOpacity={0.3}
                                            />
                                            <stop
                                                offset="95%"
                                                stopColor="#3b82f6"
                                                stopOpacity={0}
                                            />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        stroke="hsl(var(--border))"
                                    />
                                    <XAxis
                                        dataKey="time"
                                        tick={{
                                            fill: "hsl(var(--muted-foreground))",
                                            fontSize: 11,
                                        }}
                                        axisLine={{
                                            stroke: "hsl(var(--border))",
                                        }}
                                        tickFormatter={(value) => {
                                            if (!value) return "";
                                            // Backend sends "m/d H:i" (e.g. "03/12 14:30")
                                            const parts = String(value).split(" ");
                                            const [datePart, timePart] = parts;
                                            if (datePart && timePart) {
                                                const [m, d] = datePart.split("/");
                                                if (m && d) {
                                                    return `${d}/${m} ${timePart}`;
                                                }
                                            }
                                            return String(value);
                                        }}
                                    />
                                    <YAxis
                                        tick={{
                                            fill: "hsl(var(--muted-foreground))",
                                            fontSize: 11,
                                        }}
                                        axisLine={{
                                            stroke: "hsl(var(--border))",
                                        }}
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            backgroundColor: "hsl(var(--card))",
                                            border: "1px solid hsl(var(--border))",
                                            borderRadius: "var(--radius)",
                                            fontSize: "12px",
                                        }}
                                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                        formatter={
                                            ((value: any, name: any) => [
                                                `${formatCurrency(value ?? 0)} USDT`,
                                                name === "total_pnl"
                                                    ? "PNL Total"
                                                    : "Ganancia Grid",
                                            ]) as any
                                        }
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="total_pnl"
                                        stroke="#22a962"
                                        fillOpacity={1}
                                        fill="url(#colorPnl)"
                                        strokeWidth={2}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="grid_profit"
                                        stroke="#3b82f6"
                                        fillOpacity={1}
                                        fill="url(#colorGrid)"
                                        strokeWidth={2}
                                    />
                                </AreaChart>
                            </ResponsiveContainer>
                        </div>
                        <div className="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                            <div className="flex items-center gap-1.5">
                                <span className="h-2 w-2 rounded-full bg-green-500" />{" "}
                                PNL Total
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="h-2 w-2 rounded-full bg-blue-500" />{" "}
                                Ganancia Grid
                            </div>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
