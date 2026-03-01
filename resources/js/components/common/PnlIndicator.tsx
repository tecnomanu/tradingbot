import { formatCurrency, formatPercent, pnlClass } from "@/utils/formatters";

interface PnlIndicatorProps {
    value: number;
    percentage?: number;
    size?: "sm" | "md" | "lg";
    showSign?: boolean;
}

export default function PnlIndicator({
    value,
    percentage,
    size = "md",
    showSign = true,
}: PnlIndicatorProps) {
    const sizeClasses = {
        sm: "text-sm",
        md: "text-base",
        lg: "text-xl",
    };

    const sign = showSign && value > 0 ? "+" : "";

    return (
        <div className={`flex items-center gap-1.5 ${sizeClasses[size]}`}>
            <span className={pnlClass(value)}>
                {sign}
                {formatCurrency(value)} USDT
            </span>
            {percentage !== undefined && (
                <span className={`text-sm ${pnlClass(percentage)}`}>
                    ({formatPercent(percentage)})
                </span>
            )}
        </div>
    );
}
