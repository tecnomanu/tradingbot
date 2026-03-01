/**
 * Format a number as currency (USDT).
 */
export function formatCurrency(value: number | string, decimals = 2): string {
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (isNaN(num)) return "0.00";
    return new Intl.NumberFormat("en-US", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(num);
}

/**
 * Format a number as percentage with sign.
 */
export function formatPercent(value: number | string, decimals = 2): string {
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (isNaN(num)) return "0.00%";
    const sign = num > 0 ? "+" : "";
    return `${sign}${num.toFixed(decimals)}%`;
}

/**
 * Format a crypto price.
 */
export function formatPrice(value: number | string, decimals = 2): string {
    const num = typeof value === "string" ? parseFloat(value) : value;
    if (isNaN(num)) return "0";
    // Use more decimals for small values
    const actualDecimals = num < 1 ? 6 : num < 100 ? 4 : decimals;
    return formatCurrency(num, actualDecimals);
}

/**
 * Format a date string.
 */
export function formatDate(date: string | null): string {
    if (!date) return "-";
    return new Date(date).toLocaleDateString("es-AR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

/**
 * Get CSS class for PNL value.
 */
export function pnlClass(value: number): string {
    if (value > 0) return "text-green-500";
    if (value < 0) return "text-destructive";
    return "text-muted-foreground";
}

/**
 * Get CSS class for status badge.
 */
export function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        active: "badge-green",
        stopped: "badge-gray",
        error: "badge-red",
        pending: "badge-yellow",
    };
    return map[status] ?? "badge-gray";
}

/**
 * Get label for status.
 */
export function statusLabel(status: string): string {
    const map: Record<string, string> = {
        active: "Activo",
        stopped: "Detenido",
        error: "Error",
        pending: "Pendiente",
        open: "Abierta",
        filled: "Ejecutada",
        cancelled: "Cancelada",
        partially_filled: "Parcial",
    };
    return map[status] ?? status;
}

/**
 * Get label for side.
 */
export function sideLabel(side: string): string {
    const map: Record<string, string> = {
        long: "Largo",
        short: "Corto",
        neutral: "Neutral",
        buy: "Compra",
        sell: "Venta",
    };
    return map[side] ?? side;
}

/**
 * Get badge class for side.
 */
export function sideBadgeClass(side: string): string {
    const map: Record<string, string> = {
        long: "badge-green",
        short: "badge-red",
        neutral: "badge-blue",
        buy: "badge-green",
        sell: "badge-red",
    };
    return map[side] ?? "badge-gray";
}
