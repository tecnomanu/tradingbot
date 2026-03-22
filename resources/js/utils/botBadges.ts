import { cn } from "@/lib/utils";
import { sideLabel } from "@/utils/formatters";

export { sideLabel };
export type BadgeSize = "xs" | "sm" | "md";

const SIZE_CLASSES: Record<BadgeSize, string> = {
    xs: "text-[10px] px-1 py-0.5",
    sm: "text-xs px-1.5 py-0.5",
    md: "text-sm px-2 py-1",
};

const BASE = "rounded font-medium inline-flex items-center";

// --- Side ---

export function sideColor(side: string) {
    switch (side) {
        case "long":
            return { bg: "bg-green-500/15", text: "text-green-500" };
        case "short":
            return { bg: "bg-red-500/15", text: "text-red-500" };
        default:
            return { bg: "bg-yellow-500/15", text: "text-yellow-500" };
    }
}

export function sideBadgeClass(side: string, size: BadgeSize = "xs") {
    const c = sideColor(side);
    return cn(BASE, SIZE_CLASSES[size], c.bg, c.text);
}

// --- Mode (Futures / Spot) ---

export function modeColor(isFutures: boolean) {
    return isFutures
        ? { bg: "bg-blue-500/15", text: "text-blue-500" }
        : { bg: "bg-emerald-500/15", text: "text-emerald-500" };
}

export function modeLabel(isFutures: boolean) {
    return isFutures ? "Futures" : "Spot";
}

export function modeBadgeClass(isFutures: boolean, size: BadgeSize = "xs") {
    const c = modeColor(isFutures);
    return cn(BASE, SIZE_CLASSES[size], c.bg, c.text);
}

// --- Leverage ---

export function leverageClass(size: BadgeSize = "xs") {
    return cn(SIZE_CLASSES[size], "text-muted-foreground tabular-nums");
}

export function leverageLabel(leverage: number | string) {
    return `${leverage}x`;
}

// --- Margin Type ---

export function marginTypeLabel(marginType: string | null | undefined): string {
    if (!marginType) return "—";
    const lower = marginType.toLowerCase();
    if (lower === "cross" || lower === "crossed") return "Cross";
    if (lower === "isolated") return "Isolated";
    return marginType;
}

export function marginTypeBadgeClass(marginType: string | null | undefined, size: BadgeSize = "xs") {
    const lower = (marginType ?? "").toLowerCase();
    const isIsolated = lower === "isolated";
    const colors = isIsolated
        ? "bg-orange-500/15 text-orange-500"
        : "bg-purple-500/15 text-purple-500";
    return cn(BASE, SIZE_CLASSES[size], colors);
}
