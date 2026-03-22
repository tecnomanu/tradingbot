import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { timeSince } from "@/utils/timeago";
import { actionLabels } from "@/utils/activityLabels";
import {
    Activity,
    AlertTriangle,
    Brain,
    CheckCircle2,
    Clock,
    Grid3x3,
    RefreshCw,
    Shield,
    XCircle,
    Zap,
} from "lucide-react";

export interface BotHealth {
    last_sync_at: string | null;
    last_error: { action: string; message: string; at: string } | null;
    errors_24h: number;
    last_agent_action: { action: string; at: string; reason: string | null } | null;
    grid_adjusts_24h: number;
    sl_tp_changes_24h: number;
    cancellations_24h: number;
    last_error_message: string | null;
}

type HealthLevel = "healthy" | "warning" | "critical";

function deriveHealth(h: BotHealth, isActive: boolean): { level: HealthLevel; label: string } {
    if (!isActive) return { level: "warning", label: "Inactivo" };
    if (h.errors_24h >= 5) return { level: "critical", label: "Errores frecuentes" };
    if (h.last_error && Date.now() - new Date(h.last_error.at).getTime() < 3600_000) {
        return { level: "warning", label: "Error reciente" };
    }
    if (h.grid_adjusts_24h > 6) return { level: "warning", label: "Alta actividad grid" };
    if (!h.last_sync_at) return { level: "warning", label: "Sin actividad" };
    const minsSinceSync = (Date.now() - new Date(h.last_sync_at).getTime()) / 60_000;
    if (minsSinceSync > 60) return { level: "warning", label: "Sin sync reciente" };
    return { level: "healthy", label: "Operativo" };
}

const levelConfig: Record<HealthLevel, { color: string; bg: string; Icon: typeof CheckCircle2 }> = {
    healthy:  { color: "text-emerald-400", bg: "bg-emerald-500/10", Icon: CheckCircle2 },
    warning:  { color: "text-amber-400",   bg: "bg-amber-500/10",   Icon: AlertTriangle },
    critical: { color: "text-red-400",     bg: "bg-red-500/10",     Icon: XCircle },
};

export default function BotHealthPanel({ health, isActive }: { health: BotHealth; isActive: boolean }) {
    const status = deriveHealth(health, isActive);
    const cfg = levelConfig[status.level];
    const StatusIcon = cfg.Icon;

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between text-sm">
                    <span className="flex items-center gap-2">
                        <Activity className="h-4 w-4" />
                        Salud del Bot
                    </span>
                    <Badge
                        variant="outline"
                        className={`${cfg.color} border-current/20 gap-1`}
                    >
                        <StatusIcon className="h-3 w-3" />
                        {status.label}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <div className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                    {/* Last sync */}
                    <Metric
                        icon={RefreshCw}
                        label="Último sync"
                        value={health.last_sync_at ? timeSince(health.last_sync_at) : "—"}
                        muted={!health.last_sync_at}
                    />

                    {/* Errors 24h */}
                    <Metric
                        icon={XCircle}
                        label="Errores 24h"
                        value={String(health.errors_24h)}
                        alert={health.errors_24h > 0}
                    />

                    {/* Grid adjusts 24h */}
                    <Metric
                        icon={Grid3x3}
                        label="Ajustes grid 24h"
                        value={String(health.grid_adjusts_24h)}
                        alert={health.grid_adjusts_24h > 6}
                    />

                    {/* SL/TP changes */}
                    <Metric
                        icon={Shield}
                        label="Cambios SL/TP 24h"
                        value={String(health.sl_tp_changes_24h)}
                    />

                    {/* Cancellations */}
                    <Metric
                        icon={Zap}
                        label="Cancelaciones 24h"
                        value={String(health.cancellations_24h)}
                    />

                    {/* Last agent action */}
                    <Metric
                        icon={Brain}
                        label="Última acción AI"
                        value={
                            health.last_agent_action
                                ? actionLabels[health.last_agent_action.action] ?? health.last_agent_action.action
                                : "—"
                        }
                        sub={health.last_agent_action ? timeSince(health.last_agent_action.at) : undefined}
                        muted={!health.last_agent_action}
                    />

                    {/* Last error */}
                    <div className="col-span-2">
                        <Metric
                            icon={AlertTriangle}
                            label="Último error"
                            value={
                                health.last_error
                                    ? health.last_error.message || actionLabels[health.last_error.action] || health.last_error.action
                                    : "Ninguno"
                            }
                            sub={health.last_error ? timeSince(health.last_error.at) : undefined}
                            alert={!!health.last_error}
                            muted={!health.last_error}
                            truncate
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    sub,
    alert,
    muted,
    truncate,
}: {
    icon: typeof Clock;
    label: string;
    value: string;
    sub?: string;
    alert?: boolean;
    muted?: boolean;
    truncate?: boolean;
}) {
    return (
        <div className="space-y-0.5">
            <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                <Icon className="h-3 w-3 shrink-0" />
                {label}
            </div>
            <p
                className={`text-xs font-medium tabular-nums ${
                    alert ? "text-red-400" : muted ? "text-muted-foreground" : "text-foreground"
                } ${truncate ? "truncate" : ""}`}
                title={truncate ? value : undefined}
            >
                {value}
            </p>
            {sub && <p className="text-[10px] text-muted-foreground">{sub}</p>}
        </div>
    );
}
