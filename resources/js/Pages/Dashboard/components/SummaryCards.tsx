import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DashboardStats } from "@/types/bot";
import { formatCurrency, pnlClass } from "@/utils/formatters";
import { Bot, Grid3x3, TrendingUp, Wallet } from "lucide-react";

interface SummaryCardsProps {
    stats: DashboardStats;
}

export default function SummaryCards({ stats }: SummaryCardsProps) {
    const cards = [
        {
            label: "Bots Activos",
            value: `${stats.active_bots} / ${stats.total_bots}`,
            icon: Bot,
            color: "text-blue-500",
        },
        {
            label: "Inversión Total",
            value: `${formatCurrency(stats.total_investment)} USDT`,
            icon: Wallet,
            color: "text-primary",
        },
        {
            label: "PNL Total",
            value: `${stats.total_pnl > 0 ? "+" : ""}${formatCurrency(stats.total_pnl)} USDT`,
            icon: TrendingUp,
            color: pnlClass(stats.total_pnl),
        },
        {
            label: "Ganancia de Rejillas",
            value: `${formatCurrency(stats.total_grid_profit)} USDT`,
            icon: Grid3x3,
            color: "text-primary",
        },
    ];

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map((card, i) => (
                <Card
                    key={i}
                    className="animate-fade-in"
                    style={{ animationDelay: `${i * 50}ms` }}
                >
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-muted-foreground">
                            {card.label}
                        </CardTitle>
                        <card.icon className={`h-4 w-4 ${card.color}`} />
                    </CardHeader>
                    <CardContent>
                        <div className={`text-2xl font-bold ${card.color}`}>
                            {card.value}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
