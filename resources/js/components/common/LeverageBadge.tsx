interface LeverageBadgeProps {
    leverage: number;
    side?: string;
}

export default function LeverageBadge({ leverage, side }: LeverageBadgeProps) {
    const sideColor =
        side === "long"
            ? "bg-green-500"
            : side === "short"
              ? "bg-red-500"
              : "bg-blue-500";

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold text-white ${sideColor}`}
        >
            {leverage}x
            {side && (
                <span className="uppercase">
                    {side === "long"
                        ? "Largo"
                        : side === "short"
                          ? "Corto"
                          : "Neutral"}
                </span>
            )}
        </span>
    );
}
