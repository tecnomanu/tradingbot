import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { actionLabels, sourceConfig, formatActionDetails, gridAdjustReasons } from "@/utils/activityLabels";
import { timeSince } from "@/utils/timeago";
import { History, RefreshCw, AlertTriangle, CheckCircle2, XCircle, Ban } from "lucide-react";

export interface ActivityLogEntry {
    id: number;
    action: string;
    source: string;
    actor_label: string;
    details: Record<string, any> | null;
    before_state: Record<string, any> | null;
    after_state: Record<string, any> | null;
    result?: string;
    error_message?: string | null;
    created_at: string;
    created_at_fmt: string;
}

const resultConfig: Record<string, { icon: typeof CheckCircle2; color: string; label: string }> = {
    success: { icon: CheckCircle2, color: "text-emerald-400", label: "OK" },
    failed:  { icon: XCircle,      color: "text-red-400",     label: "Error" },
    partial: { icon: AlertTriangle, color: "text-amber-400",  label: "Parcial" },
    blocked: { icon: Ban,          color: "text-orange-400",  label: "Bloqueado" },
};

function StateChanges({ before, after }: { before: Record<string, any>; after: Record<string, any> }) {
    const changes = Object.keys(after).filter(
        (key) => JSON.stringify(before[key]) !== JSON.stringify(after[key])
    );
    if (changes.length === 0) return null;

    const fieldLabels: Record<string, string> = {
        status: "Estado",
        price_lower: "Precio inferior",
        price_upper: "Precio superior",
        grid_count: "Rejillas",
        investment: "Inversión",
        leverage: "Apalancamiento",
        stop_loss_price: "Stop Loss",
        take_profit_price: "Take Profit",
        grid_mode: "Modo grid",
    };

    return (
        <div className="mt-1.5 flex flex-wrap gap-1.5">
            {changes.map((key) => (
                <span
                    key={key}
                    className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[10px] font-mono"
                >
                    <span className="text-muted-foreground">{fieldLabels[key] ?? key}:</span>
                    <span className="text-red-400 line-through">{String(before[key] ?? "—")}</span>
                    <span className="text-green-400">{String(after[key] ?? "—")}</span>
                </span>
            ))}
        </div>
    );
}

export default function BotActivityLog({ logs }: { logs: ActivityLogEntry[] }) {
    if (logs.length === 0) {
        return (
            <Card>
                <CardContent className="flex h-40 items-center justify-center">
                    <div className="text-center text-sm text-muted-foreground">
                        <History className="mx-auto mb-2 h-8 w-8 opacity-50" />
                        <p>Sin actividad registrada aún</p>
                        <p className="text-xs mt-1">Las acciones sobre el bot aparecerán aquí</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm">
                    <History className="h-4 w-4" />
                    Historial de actividad ({logs.length})
                </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <div className="max-h-[600px] overflow-y-auto">
                    <div className="relative">
                        <div className="absolute left-[19px] top-0 bottom-0 w-px bg-border" />
                        <div className="space-y-0">
                            {logs.map((log) => {
                                const cfg = sourceConfig[log.source] ?? sourceConfig.system;
                                const Icon = cfg.icon;
                                const detailStr = formatActionDetails(log.action, log.details);

                                return (
                                    <div key={log.id} className="relative flex gap-3 py-2.5 pl-0 group">
                                        <div className={`relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${cfg.bg} ring-4 ring-background`}>
                                            <Icon className={`h-4 w-4 ${cfg.color}`} />
                                        </div>
                                        <div className="flex-1 min-w-0 pt-0.5">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-medium">
                                                    {actionLabels[log.action] ?? log.action}
                                                </span>
                                                <Badge variant="outline" className="text-[10px] px-1.5 py-0 h-4 font-normal">
                                                    {log.actor_label}
                                                </Badge>
                                                {log.result && log.result !== "success" && (() => {
                                                    const rc = resultConfig[log.result] ?? resultConfig.failed;
                                                    const ResultIcon = rc.icon;
                                                    return (
                                                        <span className={`inline-flex items-center gap-0.5 text-[10px] ${rc.color}`}>
                                                            <ResultIcon className="h-3 w-3" />
                                                            {rc.label}
                                                        </span>
                                                    );
                                                })()}
                                                <span className="text-xs text-muted-foreground ml-auto shrink-0">
                                                    {log.created_at_fmt} · {timeSince(log.created_at)}
                                                </span>
                                            </div>
                                            {log.action === "grid_adjusted" && log.details?.reason && (
                                                <div className="flex items-center gap-1.5 mt-0.5">
                                                    <RefreshCw className="h-3 w-3 text-blue-400" />
                                                    <Badge variant="secondary" className="text-[10px] px-1.5 py-0 h-4">
                                                        {gridAdjustReasons[log.details.reason] ?? log.details.reason}
                                                    </Badge>
                                                </div>
                                            )}
                                            {detailStr && (
                                                <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                                    {detailStr}
                                                </p>
                                            )}
                                            {log.error_message && (
                                                <p className="text-[11px] text-red-400 mt-0.5 truncate" title={log.error_message}>
                                                    {log.error_message}
                                                </p>
                                            )}
                                            {log.before_state && log.after_state && (
                                                <StateChanges before={log.before_state} after={log.after_state} />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
