import { Brain, Monitor, Server, User, Zap } from "lucide-react";

export const actionLabels: Record<string, string> = {
    bot_created: "Bot creado",
    bot_started: "Bot iniciado",
    bot_stopped: "Bot detenido",
    bot_sl_tp_alert: "Alerta SL/TP",
    bot_stop_blocked: "Stop bloqueado (auto)",
    bot_updated: "Bot actualizado",
    bot_deleted: "Bot eliminado",
    sl_set: "Stop-Loss configurado",
    tp_set: "Take-Profit configurado",
    orders_cancelled: "Órdenes canceladas",
    order_placed: "Orden colocada",
    order_cancelled: "Orden cancelada",
    position_closed: "Posición cerrada",
    grid_adjusted: "Grid ajustado",
    risk_guard_triggered: "Risk Guard disparado",
    soft_guard_triggered: "Soft Guard activado",
    soft_guard_cleared: "Soft Guard desactivado",
    hard_guard_triggered: "Hard Guard disparado",
    reentry_success: "Re-entry exitoso",
    reentry_blocked: "Re-entry bloqueado",
    exchange_error: "Error de exchange",
    price_out_of_range: "Precio fuera de rango",
    leverage_changed: "Apalancamiento cambiado",
};

export const gridAdjustReasons: Record<string, string> = {
    all_orders_filled: "Todas las órdenes ejecutadas (auto-rebuild)",
    price_outside_range: "Precio fuera del rango",
    volatility_shift: "Cambio de volatilidad",
    trend_change: "Cambio de tendencia",
    manual_action: "Ajuste manual del usuario",
    protection_mode: "Modo protección",
    bot_recovery: "Recuperación del bot",
    unknown: "Sin motivo especificado",
};

export const actionColors: Record<string, string> = {
    bot_created: "text-blue-400",
    bot_started: "text-emerald-400",
    bot_stopped: "text-red-400",
    bot_sl_tp_alert: "text-orange-400",
    bot_stop_blocked: "text-amber-400",
    bot_updated: "text-sky-400",
    sl_set: "text-yellow-400",
    tp_set: "text-emerald-400",
    orders_cancelled: "text-orange-400",
    position_closed: "text-red-400",
    grid_adjusted: "text-blue-400",
    risk_guard_triggered: "text-red-500",
    soft_guard_triggered: "text-amber-500",
    soft_guard_cleared: "text-green-400",
    hard_guard_triggered: "text-red-600",
    reentry_success: "text-emerald-500",
    reentry_blocked: "text-orange-500",
    exchange_error: "text-red-400",
    price_out_of_range: "text-orange-400",
    leverage_changed: "text-purple-400",
};

export const sourceConfig: Record<string, { icon: typeof User; color: string; bg: string; border: string }> = {
    user:   { icon: User,    color: "text-blue-500",   bg: "bg-blue-500/10",   border: "border-blue-500/20" },
    api:    { icon: Zap,     color: "text-amber-500",  bg: "bg-amber-500/10",  border: "border-amber-500/20" },
    agent:  { icon: Brain,   color: "text-purple-500", bg: "bg-purple-500/10", border: "border-purple-500/20" },
    system: { icon: Server,  color: "text-gray-400",   bg: "bg-gray-500/10",   border: "border-gray-500/20" },
    manual: { icon: Monitor, color: "text-cyan-500",   bg: "bg-cyan-500/10",   border: "border-cyan-500/20" },
};

export function getActionLabel(action: string): string {
    return actionLabels[action] ?? action;
}

export function getActionColor(action: string): string {
    return actionColors[action] ?? "text-muted-foreground";
}

export function formatActionDetails(action: string, details: Record<string, any> | null): string {
    if (!details) return "";

    if (action === "grid_adjusted") {
        return formatGridAdjustedDetails(details);
    }

    if (action === "price_out_of_range") {
        return formatPriceOutOfRangeDetails(details);
    }

    const parts: string[] = [];
    if (details.reason) parts.push(details.reason);
    if (details.price !== undefined)
        parts.push(`$${Number(details.price).toLocaleString()}${details.previous ? ` (anterior: $${Number(details.previous).toLocaleString()})` : ""}`);
    if (details.current_price !== undefined) parts.push(`Precio: $${Number(details.current_price).toLocaleString()}`);
    if (details.stop_loss_price !== undefined && details.stop_loss_price) parts.push(`SL: $${Number(details.stop_loss_price).toLocaleString()}`);
    if (details.take_profit_price !== undefined && details.take_profit_price) parts.push(`TP: $${Number(details.take_profit_price).toLocaleString()}`);
    if (details.cancelled_count !== undefined) parts.push(`${details.cancelled_count} órdenes`);
    if (details.close_side) parts.push(`${details.close_side} ${Math.abs(details.size ?? 0)}`);
    if (details.unrealized_pnl !== undefined) parts.push(`PNL: ${details.unrealized_pnl}`);
    if (details.fields_changed) parts.push(`Campos: ${details.fields_changed.join(", ")}`);
    if (details.was_active) parts.push("Estaba activo → reiniciado");
    if (details.new_lower !== undefined) parts.push(`${details.new_lower} – ${details.new_upper}`);
    if (details.symbol) parts.push(details.symbol);
    if (details.side) parts.push(`Lado: ${details.side}`);
    if (details.grid_count) parts.push(`Grids: ${details.grid_count}`);
    if (details.investment) parts.push(`Inversión: ${details.investment} USDT`);
    if (parts.length > 0) return parts.join(" · ");
    const filtered = Object.entries(details)
        .filter(([, v]) => v !== null && v !== undefined)
        .map(([k, v]) => `${k}: ${typeof v === "object" ? JSON.stringify(v) : v}`);
    return filtered.join(", ");
}

function formatGridAdjustedDetails(details: Record<string, any>): string {
    const parts: string[] = [];
    const reasonKey = details.reason ?? details.reason_label ?? null;
    if (reasonKey) {
        const label = gridAdjustReasons[reasonKey] ?? reasonKey;
        parts.push(`Motivo: ${label}`);
    }
    if (details.old_range && details.new_range) {
        parts.push(`${details.old_range} → ${details.new_range}`);
    }
    if (details.current_price !== undefined) {
        parts.push(`Precio: $${Number(details.current_price).toLocaleString()}`);
    }
    if (details.orders_placed !== undefined) {
        parts.push(`${details.orders_placed} órdenes colocadas`);
    }
    return parts.length > 0 ? parts.join(" · ") : "Grid ajustado";
}

const oorReasons: Record<string, string> = {
    price_deviation_minor: "Desviación menor",
    sustained_breakout: "Ruptura sostenida",
    price_breakout_protection: "Protección por ruptura",
};

function formatPriceOutOfRangeDetails(details: Record<string, any>): string {
    const parts: string[] = [];
    if (details.reason) parts.push(oorReasons[details.reason] ?? details.reason);
    const dir = details.direction === "below" ? "↓ debajo" : "↑ encima";
    if (details.deviation_pct !== undefined) parts.push(`${dir} ${details.deviation_pct}%`);
    if (details.price !== undefined) parts.push(`$${Number(details.price).toLocaleString()}`);
    if (details.action_taken && details.action_taken !== "none") parts.push(`→ ${details.action_taken}`);
    if (details.streak !== undefined) parts.push(`${details.streak} min`);
    return parts.join(" · ");
}

export function formatActionsTaken(actions: string[]): string {
    return actions.map((a) => actionLabels[a] || a).join(", ");
}
