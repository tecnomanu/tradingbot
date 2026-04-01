import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { Info } from "lucide-react";

interface MetricInfoProps {
    text: string;
    side?: "top" | "bottom" | "left" | "right";
}

/**
 * Inline info icon with a tooltip explaining a metric.
 * Usage: <MetricInfo text="Ganancia generada por las órdenes ejecutadas dentro del rango." />
 */
export function MetricInfo({ text, side = "top" }: MetricInfoProps) {
    return (
        <TooltipProvider delayDuration={200}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex cursor-help text-muted-foreground/40 hover:text-muted-foreground/80 transition-colors ml-1 align-middle shrink-0">
                        <Info className="h-3 w-3" aria-hidden="true" />
                        <span className="sr-only">Más información sobre {text.split(" ").slice(0, 3).join(" ")}</span>
                    </span>
                </TooltipTrigger>
                <TooltipContent
                    side={side}
                    className="max-w-[240px] text-xs leading-relaxed"
                >
                    {text}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
