import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { AgentImpact } from "@/types/bot";
import { actionLabels } from "@/utils/activityLabels";
import { Brain, Server, Clock, TrendingUp, TrendingDown, BarChart3 } from "lucide-react";

interface Props {
    impact: AgentImpact;
}

function fmtUsd(v: number): string {
    const sign = v >= 0 ? "+" : "";
    return `${sign}${v.toFixed(2)} USDT`;
}

function fmtRate(v: number): string {
    const sign = v >= 0 ? "+" : "";
    return `${sign}${v.toFixed(4)}/h`;
}

function PnlColor({ value }: { value: number }) {
    const cls = value > 0 ? "text-emerald-400" : value < 0 ? "text-red-400" : "text-muted-foreground";
    return <span className={cls}>{fmtUsd(value)}</span>;
}

export default function AgentImpactPanel({ impact }: Props) {
    const { agent, system, agent_actions, runtime_hours, agent_coverage_pct, snapshots_used, data_since } = impact;

    if (snapshots_used < 4) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <BarChart3 className="h-4 w-4" />
                        Bot vs AI Agent
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-xs text-muted-foreground">
                        Datos insuficientes. Se necesitan al menos 20 minutos de snapshots para comparar.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const betterBucket = agent.pnl_per_hour > system.pnl_per_hour ? "agent" : "system";

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <BarChart3 className="h-4 w-4" />
                        Bot solo vs Bot + AI Agent
                    </CardTitle>
                    <Badge variant="outline" className="text-[10px] font-normal">
                        {runtime_hours}h analizadas
                    </Badge>
                </div>
                <p className="text-[11px] text-muted-foreground mt-1">
                    Ventana de influencia: {impact.influence_window_min} min post-acción del agente.
                    {data_since && ` Datos desde ${new Date(data_since).toLocaleDateString()}.`}
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Comparison table */}
                <div className="grid grid-cols-3 gap-2 text-xs">
                    <div />
                    <div className="flex items-center gap-1 font-medium text-muted-foreground justify-center">
                        <Server className="h-3 w-3" /> Bot solo
                    </div>
                    <div className="flex items-center gap-1 font-medium text-muted-foreground justify-center">
                        <Brain className="h-3 w-3" /> Con AI Agent
                    </div>

                    {/* PNL total */}
                    <div className="text-muted-foreground flex items-center gap-1">
                        PNL acumulado
                    </div>
                    <div className="text-center font-mono">
                        <PnlColor value={system.pnl} />
                    </div>
                    <div className="text-center font-mono">
                        <PnlColor value={agent.pnl} />
                    </div>

                    {/* PNL/hour */}
                    <div className="text-muted-foreground flex items-center gap-1">
                        PNL / hora
                    </div>
                    <div className={`text-center font-mono ${betterBucket === "system" ? "text-emerald-400 font-semibold" : ""}`}>
                        {fmtRate(system.pnl_per_hour)}
                    </div>
                    <div className={`text-center font-mono ${betterBucket === "agent" ? "text-emerald-400 font-semibold" : ""}`}>
                        {fmtRate(agent.pnl_per_hour)}
                    </div>

                    {/* Hours */}
                    <div className="text-muted-foreground flex items-center gap-1">
                        <Clock className="h-3 w-3" /> Horas
                    </div>
                    <div className="text-center font-mono">
                        {system.hours}h
                    </div>
                    <div className="text-center font-mono">
                        {agent.hours}h
                    </div>

                    {/* Intervals */}
                    <div className="text-muted-foreground">Intervalos</div>
                    <div className="text-center font-mono">{system.intervals}</div>
                    <div className="text-center font-mono">{agent.intervals}</div>
                </div>

                {/* Coverage bar */}
                <div>
                    <div className="flex justify-between text-[10px] text-muted-foreground mb-1">
                        <span>Cobertura del agente: {agent_coverage_pct}%</span>
                    </div>
                    <div className="h-2 rounded-full bg-muted overflow-hidden">
                        <div
                            className="h-full bg-blue-500 rounded-full transition-all"
                            style={{ width: `${Math.min(agent_coverage_pct, 100)}%` }}
                        />
                    </div>
                    <div className="flex justify-between text-[10px] text-muted-foreground mt-0.5">
                        <span>Solo bot</span>
                        <span>Con agente</span>
                    </div>
                </div>

                {/* Agent actions breakdown */}
                {agent_actions.total > 0 && (
                    <div>
                        <p className="text-xs font-medium text-muted-foreground mb-1.5">
                            Intervenciones del agente ({agent_actions.total})
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {Object.entries(agent_actions.by_type).map(([action, count]) => (
                                <Badge key={action} variant="outline" className="text-[10px] gap-1">
                                    {actionLabels[action] ?? action}
                                    <span className="font-mono font-semibold">{count}</span>
                                </Badge>
                            ))}
                        </div>
                        <div className="flex gap-3 mt-1.5 text-[10px] text-muted-foreground">
                            <span className="text-emerald-400">
                                ✓ {impact.agent_actions_success} exitosas
                            </span>
                            {impact.agent_actions_failed > 0 && (
                                <span className="text-red-400">
                                    ✗ {impact.agent_actions_failed} fallidas
                                </span>
                            )}
                        </div>
                    </div>
                )}

                {agent_actions.total === 0 && (
                    <p className="text-xs text-muted-foreground italic">
                        El agente no ha realizado intervenciones aún. Todas las métricas son de operación autónoma del grid.
                    </p>
                )}

                {/* Verdict (subtle, non-conclusive) */}
                {agent_actions.total > 0 && runtime_hours >= 1 && (
                    <div className="border-t pt-2">
                        <div className="flex items-center gap-1.5 text-xs">
                            {betterBucket === "agent" ? (
                                <>
                                    <TrendingUp className="h-3.5 w-3.5 text-emerald-400" />
                                    <span className="text-muted-foreground">
                                        Períodos con agente muestran mejor PNL/hora
                                        <span className="text-[10px] ml-1">(correlación, no causalidad)</span>
                                    </span>
                                </>
                            ) : (
                                <>
                                    <TrendingDown className="h-3.5 w-3.5 text-amber-400" />
                                    <span className="text-muted-foreground">
                                        Períodos sin agente muestran igual o mejor PNL/hora
                                        <span className="text-[10px] ml-1">(puede indicar que el agente interviene en momentos difíciles)</span>
                                    </span>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
