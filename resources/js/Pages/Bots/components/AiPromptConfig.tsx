import { Bot } from "@/types/bot";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { router } from "@inertiajs/react";
import { Brain, CheckCircle, FlaskConical, Loader2, Save, PowerOff } from "lucide-react";
import { useState } from "react";

const PERSONALITY_PRESETS: Record<string, { label: string; description: string; prompt: string }> = {
    conservative: {
        label: "Conservador",
        description: "Prioriza preservar capital. Solo actúa con evidencia técnica abrumadora. Casi nunca ajusta grid.",
        prompt: "Cautious grid trading supervisor. Capital preservation is the absolute priority.\n\n## PRINCIPLES\n- Only act with overwhelming multi-indicator evidence (RSI extreme + MACD + Bollinger all aligned).\n- Tight SL always. Passive grid — almost never adjust.\n- Only adjust_grid in extreme prolonged misalignment (position% > 95 or < 5 sustained).\n- Most consultations should end with no action taken.\n- Never chase price, never widen grid, never remove protections.",
    },
    moderate: {
        label: "Moderado",
        description: "Supervisión equilibrada. Interviene solo con señales claras y convergentes. Prioriza estabilidad.",
        prompt: "Expert crypto grid trading supervisor. Moderate/supervisory style.\n\n## PRINCIPLES\n- Stability over optimization. Protect capital before chasing profit.\n- Tolerate normal market noise. BTC fluctuations of 1-3% are routine — do NOT react.\n- Intervene only when multiple indicators converge on a clear signal.\n- Never chase price or recenter grid for small moves.\n- When in doubt, report status and take NO action.\n\n## INTERVENTION CRITERIA\n- Only adjust grid when price is truly outside the effective range (position% > 90 or < 10) AND confirmed by RSI + trend.\n- Only change SL/TP when current values are clearly inadequate (e.g., no SL set, or SL too far from price with large exposure).\n- Do NOT adjust SL/TP to \"optimize\" — only to protect against genuine risk.\n- Do NOT reconfigure the bot due to minor RSI moves (40-60 range is neutral — ignore it).\n- If the bot is profitable and within grid range, prefer \"no changes\" over any adjustment.\n\n## FREQUENCY\n- Prefer reporting over acting. Most consultations should end with \"sin cambios necesarios\".\n- Never adjust grid, SL, and TP in the same consultation unless facing an emergency.",
    },
    aggressive: {
        label: "Agresivo",
        description: "Maximiza profit activamente. Ajusta grid y SL/TP siguiendo tendencia. Mayor intervención.",
        prompt: "Aggressive grid trading supervisor. Maximize profit actively.\n\n## STYLE\n- Adjust grid when position% > 85 or < 15, recenter around price following trend.\n- Tight SL, wide TP. Bullish (RSI>60 + MACD positive) → shift grid up.\n- Bearish → narrow grid, tighten protections.\n- Neutral zone (15-85% + RSI 40-60) → report only, do not adjust.\n- Every action must be justified with specific numbers.",
    },
};

const DEFAULT_USER_PROMPT = "Check Bot #{bot_id} ({symbol}) — {now} UTC. Call get_bot_status + get_market_data, analyze, act if needed, finish with done().";

const INTERVAL_OPTIONS = [
    { value: 5, label: "5 min" },
    { value: 10, label: "10 min" },
    { value: 15, label: "15 min" },
    { value: 30, label: "30 min" },
    { value: 60, label: "1 hora" },
];

function detectPreset(prompt: string | null): string {
    if (!prompt) return "moderate";
    // Only match a preset when the stored text is exactly that preset's prompt
    for (const [key, preset] of Object.entries(PERSONALITY_PRESETS)) {
        if (prompt.trim() === preset.prompt.trim()) return key;
    }
    return "custom";
}

export default function AiPromptConfig({ bot }: { bot: Bot }) {
    const detectedPreset = detectPreset(bot.ai_system_prompt);
    const [agentEnabled, setAgentEnabled] = useState(bot.ai_agent_enabled ?? true);
    const [activePreset, setActivePreset] = useState(detectedPreset);
    const [customPrompt, setCustomPrompt] = useState(bot.ai_system_prompt ?? "");
    const [userPrompt, setUserPrompt] = useState(bot.ai_user_prompt ?? "");
    const [interval, setInterval] = useState(bot.ai_consultation_interval || 15);
    const [notifyTelegram, setNotifyTelegram] = useState(bot.ai_notify_telegram ?? false);
    const [notifyEvents, setNotifyEvents] = useState<string[]>(bot.ai_notify_events ?? ["grid_adjusted", "bot_stopped", "risk_guard_triggered", "stop_loss_set", "position_closed"]);
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [review, setReview] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [showCustom, setShowCustom] = useState(detectedPreset === "custom");

    const currentPrompt = showCustom
        ? customPrompt
        : (PERSONALITY_PRESETS[activePreset]?.prompt ?? PERSONALITY_PRESETS.moderate.prompt);

    const handlePresetChange = (preset: string) => {
        setActivePreset(preset);
        if (preset !== "custom") {
            setShowCustom(false);
            setCustomPrompt(PERSONALITY_PRESETS[preset]?.prompt ?? "");
        } else {
            if (!showCustom) {
                // Pre-fill with the currently saved DB prompt so the user
                // doesn't lose their text when switching to custom mode
                const savedIsPreset = Object.values(PERSONALITY_PRESETS).some(
                    (p) => (bot.ai_system_prompt ?? "").trim() === p.prompt.trim()
                );
                setCustomPrompt(savedIsPreset ? "" : (bot.ai_system_prompt ?? ""));
            }
            setShowCustom(true);
        }
        setSaved(false);
    };

    const handleSave = () => {
        setSaving(true);
        setSaved(false);
        setSaveError(null);
        router.put(
            `/ai-agent/bots/${bot.id}/prompts`,
            {
                ai_agent_enabled: agentEnabled,
                ai_system_prompt: agentEnabled ? (currentPrompt || null) : null,
                ai_user_prompt: agentEnabled ? (userPrompt || null) : null,
                ai_consultation_interval: interval,
                ai_notify_telegram: notifyTelegram,
                ai_notify_events: notifyEvents,
            },
            {
                preserveScroll: true,
                onSuccess: () => { setSaved(true); setSaveError(null); },
                onError: (errors) => {
                    setSaved(false);
                    const msgs = Object.values(errors).flat().join(" · ");
                    setSaveError(msgs || "Error al guardar. Revisá los campos.");
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleTest = async () => {
        setTesting(true);
        setReview(null);
        try {
            const res = await fetch(`/ai-agent/bots/${bot.id}/test-prompts`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "",
                },
                body: JSON.stringify({
                    ai_system_prompt: currentPrompt || null,
                    ai_user_prompt: userPrompt || null,
                }),
            });

            const text = await res.text();
            let data: any;
            try {
                data = JSON.parse(text);
            } catch {
                setReview(`Error: respuesta inesperada del servidor (HTTP ${res.status})`);
                return;
            }

            setReview(data.review || data.error || "Sin respuesta");
        } catch (e: any) {
            setReview("Error: " + e.message);
        } finally {
            setTesting(false);
        }
    };

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Brain className="h-5 w-5" />
                        Personalidad del Agente
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Elegí el estilo de trading del agente AI. Las reglas operativas (workflow, tools, formato) son fijas y no se muestran.
                    </p>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Agent enabled/disabled toggle */}
                    <div className={`flex items-center justify-between p-3 rounded-lg border-2 transition-all ${
                        !agentEnabled ? "border-destructive/60 bg-destructive/5" : "border-border"
                    }`}>
                        <div className="flex items-center gap-2">
                            <PowerOff className={`h-4 w-4 ${!agentEnabled ? "text-destructive" : "text-muted-foreground"}`} />
                            <div>
                                <div className="font-medium text-sm">Agente AI</div>
                                <div className="text-xs text-muted-foreground">
                                    {agentEnabled ? "Activo — el bot consulta el agente periódicamente" : "Desactivado — el bot opera sin consultar el agente"}
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={agentEnabled}
                            onClick={() => { setAgentEnabled(!agentEnabled); setSaved(false); }}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                agentEnabled ? "bg-primary" : "bg-destructive/70"
                            }`}
                        >
                            <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                agentEnabled ? "translate-x-6" : "translate-x-1"
                            }`} />
                        </button>
                    </div>

                    {agentEnabled && (
                    <div className="space-y-3">
                        <Label>Estilo de Trading</Label>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {Object.entries(PERSONALITY_PRESETS).map(([key, preset]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => handlePresetChange(key)}
                                    className={`text-left p-3 rounded-lg border-2 transition-all ${
                                        activePreset === key && !showCustom
                                            ? "border-primary bg-primary/10"
                                            : "border-border hover:border-primary/50"
                                    }`}
                                >
                                    <div className="font-medium text-sm">{preset.label}</div>
                                    <div className="text-xs text-muted-foreground mt-1">{preset.description}</div>
                                </button>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => handlePresetChange("custom")}
                            className={`w-full text-left p-3 rounded-lg border-2 transition-all ${
                                showCustom
                                    ? "border-primary bg-primary/10"
                                    : "border-border hover:border-primary/50"
                            }`}
                        >
                            <div className="font-medium text-sm">Personalizado</div>
                            <div className="text-xs text-muted-foreground mt-1">Escribí tu propia personalidad y reglas de trading.</div>
                        </button>
                    </div>
                    )}

                    {agentEnabled && showCustom && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="custom-prompt">Personalidad personalizada</Label>
                                <span className={`text-[10px] tabular-nums ${customPrompt.length > 18000 ? "text-destructive" : "text-muted-foreground"}`}>
                                    {customPrompt.length.toLocaleString()} / 20 000
                                </span>
                            </div>
                            <Textarea
                                id="custom-prompt"
                                value={customPrompt}
                                onChange={(e) => { setCustomPrompt(e.target.value); setSaved(false); setSaveError(null); }}
                                placeholder="You are a crypto grid trading bot supervisor..."
                                rows={12}
                                className="font-mono text-sm"
                            />
                            <p className="text-xs text-muted-foreground">
                                Definí la personalidad, estilo y criterios de decisión. Las instrucciones de workflow y tools se agregan automáticamente.
                            </p>
                        </div>
                    )}

                    {agentEnabled && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="user-prompt">Mensaje Inicial</Label>
                            <span className={`text-[10px] tabular-nums ${userPrompt.length > 4500 ? "text-destructive" : "text-muted-foreground"}`}>
                                {userPrompt.length.toLocaleString()} / 5 000
                            </span>
                        </div>
                        <Textarea
                            id="user-prompt"
                            value={userPrompt}
                            onChange={(e) => { setUserPrompt(e.target.value); setSaved(false); setSaveError(null); }}
                            placeholder={DEFAULT_USER_PROMPT}
                            rows={4}
                            className="font-mono text-sm"
                        />
                        <p className="text-xs text-muted-foreground">
                            El mensaje que recibe el agente en cada consulta. Variables: <code className="text-xs bg-muted px-1 rounded">{"{bot_id}"}</code>, <code className="text-xs bg-muted px-1 rounded">{"{symbol}"}</code>, <code className="text-xs bg-muted px-1 rounded">{"{now}"}</code>
                        </p>
                    </div>
                    )}

                    {agentEnabled && (
                    <div className="space-y-2">
                        <Label>Intervalo de consulta</Label>
                        <div className="flex flex-wrap gap-2">
                            {INTERVAL_OPTIONS.map((opt) => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => { setInterval(opt.value); setSaved(false); }}
                                    className={`px-3 py-1.5 rounded-md text-sm font-medium border transition-all ${
                                        interval === opt.value
                                            ? "border-primary bg-primary/10 text-primary"
                                            : "border-border hover:border-primary/50 text-muted-foreground"
                                    }`}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Cada cuánto el agente AI revisa este bot automáticamente.
                        </p>
                    </div>
                    )}

                    <hr className="border-border" />

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <Label>Notificaciones Telegram</Label>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Recibí alertas cuando el agente ejecute acciones.
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={notifyTelegram}
                                onClick={() => { setNotifyTelegram(!notifyTelegram); setSaved(false); }}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    notifyTelegram ? "bg-primary" : "bg-muted"
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    notifyTelegram ? "translate-x-6" : "translate-x-1"
                                }`} />
                            </button>
                        </div>
                        {notifyTelegram && (
                            <div className="space-y-2 pl-0.5">
                                <p className="text-xs font-medium text-muted-foreground">Notificar cuando:</p>
                                <div className="grid grid-cols-2 gap-2">
                                    {[
                                        { key: "grid_adjusted", label: "Grid ajustado" },
                                        { key: "bot_stopped", label: "Bot detenido" },
                                        { key: "risk_guard_triggered", label: "Risk Guard" },
                                        { key: "soft_guard_triggered", label: "Soft Guard" },
                                        { key: "hard_guard_triggered", label: "Hard Guard" },
                                        { key: "reentry_success", label: "Re-entry exitoso" },
                                        { key: "reentry_blocked", label: "Re-entry bloqueado" },
                                        { key: "stop_loss_set", label: "Stop Loss" },
                                        { key: "take_profit_set", label: "Take Profit" },
                                        { key: "position_closed", label: "Posición cerrada" },
                                        { key: "orders_cancelled", label: "Órdenes canceladas" },
                                    ].map((evt) => (
                                        <label key={evt.key} className="flex items-center gap-2 text-sm cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={notifyEvents.includes(evt.key)}
                                                onChange={(e) => {
                                                    setNotifyEvents(
                                                        e.target.checked
                                                            ? [...notifyEvents, evt.key]
                                                            : notifyEvents.filter((k) => k !== evt.key)
                                                    );
                                                    setSaved(false);
                                                }}
                                                className="rounded border-border"
                                            />
                                            {evt.label}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="space-y-2">
                        <div className="flex flex-wrap gap-2 items-center">
                            <Button onClick={handleSave} disabled={saving} className="gap-1.5">
                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                {saving ? "Guardando..." : "Guardar"}
                            </Button>
                            <Button onClick={handleTest} disabled={testing || !agentEnabled} variant="secondary" className="gap-1.5">
                                {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <FlaskConical className="h-4 w-4" />}
                                {testing ? "Analizando..." : "Test con IA"}
                            </Button>
                            {saved && (
                                <span className="flex items-center gap-1 text-sm text-emerald-400">
                                    <CheckCircle className="h-4 w-4" /> Guardado
                                </span>
                            )}
                        </div>
                        {saveError && (
                            <p className="text-xs text-destructive bg-destructive/10 border border-destructive/30 rounded px-3 py-2">
                                {saveError}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {review && (
                <Card className="border-primary/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FlaskConical className="h-4 w-4" />
                            Evaluación de la configuración
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="prose prose-sm prose-invert max-w-none whitespace-pre-wrap text-sm">
                            {review}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
