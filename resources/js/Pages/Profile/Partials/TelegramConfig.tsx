import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import axios from "axios";
import { Check, ClipboardCopy, ExternalLink, Key, Link2, Loader2, MessageCircle, Send, Unlink } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

interface Props {
    chatId: string | null;
    connected: boolean;
}

export default function TelegramConfig({ chatId: initialChatId, connected: initialConnected }: Props) {
    const [connected, setConnected] = useState(initialConnected);
    const [chatId, setChatId] = useState(initialChatId ?? "");
    const [linking, setLinking] = useState(false);
    const [deepLink, setDeepLink] = useState<string | null>(null);
    const [linkToken, setLinkToken] = useState<string | null>(null);
    const [polling, setPolling] = useState(false);
    const [autoPolling, setAutoPolling] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testSent, setTestSent] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const [manualMode, setManualMode] = useState(false);
    const [savingManual, setSavingManual] = useState(false);
    const [copied, setCopied] = useState(false);
    const pollIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopAutoPolling = useCallback(() => {
        setAutoPolling(false);
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }
    }, []);

    useEffect(() => () => { if (pollIntervalRef.current) clearInterval(pollIntervalRef.current); }, []);

    const startLink = useCallback(async () => {
        setLinking(true);
        try {
            const { data } = await axios.post(route("telegram.link-token"));
            setLinkToken(data.token);
            setDeepLink(data.deep_link ?? `https://t.me/trading_wizardgpt_bot?start=${data.token}`);
        } catch {
            alert("Error generando token de vinculación.");
        } finally {
            setLinking(false);
        }
    }, []);

    const doPoll = useCallback(async () => {
        const { data } = await axios.post(route("telegram.poll"));
        if (data.connected) {
            setConnected(true);
            setChatId(data.chat_id);
            setDeepLink(null);
            setLinkToken(null);
            stopAutoPolling();
            return true;
        }
        return false;
    }, [stopAutoPolling]);

    const pollOnce = useCallback(async () => {
        setPolling(true);
        try {
            const found = await doPoll();
            if (!found) {
                alert("Aún no detectado. Asegurate de haber enviado /start con el código al bot y volvé a intentar.");
            }
        } catch {
            alert("Error verificando conexión.");
        } finally {
            setPolling(false);
        }
    }, [doPoll]);

    const startAutoPolling = useCallback(() => {
        setAutoPolling(true);
        pollIntervalRef.current = setInterval(async () => {
            try { await doPoll(); } catch { /* retry next interval */ }
        }, 4000);
    }, [doPoll]);

    const copyCode = useCallback(async (text: string) => {
        await navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }, []);

    const saveManualChatId = useCallback(async () => {
        if (!chatId.trim()) return;
        setSavingManual(true);
        try {
            await axios.post(route("telegram.set-chat-id"), { chat_id: chatId.trim() });
            setConnected(true);
            setManualMode(false);
        } catch {
            alert("Error guardando Chat ID.");
        } finally {
            setSavingManual(false);
        }
    }, [chatId]);

    const sendTest = useCallback(async () => {
        setTesting(true);
        setTestSent(false);
        try {
            const { data } = await axios.post(route("telegram.test"));
            setTestSent(data.success);
            if (!data.success) alert("No se pudo enviar. Verificá que el Chat ID sea correcto.");
        } catch {
            alert("Error enviando mensaje de prueba.");
        } finally {
            setTesting(false);
        }
    }, []);

    const disconnect = useCallback(async () => {
        if (!confirm("¿Desconectar Telegram? No recibirás más notificaciones.")) return;
        setDisconnecting(true);
        try {
            await axios.post(route("telegram.disconnect"));
            setConnected(false);
            setChatId("");
            setDeepLink(null);
            setLinkToken(null);
        } catch {
            alert("Error desconectando.");
        } finally {
            setDisconnecting(false);
        }
    }, []);

    if (connected) {
        return (
            <div className="space-y-4">
                <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <Check className="h-5 w-5 text-emerald-500" />
                        <span className="font-medium text-emerald-400">
                            Telegram conectado
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Chat ID:{" "}
                        <code className="bg-muted px-1.5 py-0.5 rounded text-xs font-mono">
                            {chatId}
                        </code>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Activá las notificaciones en cada bot desde su pestaña
                        AI Agent.
                    </p>
                    <div className="rounded border border-emerald-500/20 bg-muted/30 p-3 mt-2">
                        <p className="text-xs font-medium text-muted-foreground mb-2">
                            Eventos disponibles para notificaciones
                        </p>
                        <ul className="text-xs text-muted-foreground space-y-1">
                            <li>• Ajuste de grid</li>
                            <li>• Bot detenido</li>
                            <li>• Stop loss configurado</li>
                            <li>• Take profit configurado</li>
                            <li>• Posición cerrada</li>
                            <li>• Órdenes canceladas</li>
                        </ul>
                        <p className="text-xs text-muted-foreground mt-2">
                            Configurá qué eventos recibir en cada bot.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" onClick={sendTest} disabled={testing} className="gap-1.5">
                        {testing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Send className="h-3.5 w-3.5" />}
                        {testing ? "Enviando..." : "Enviar prueba"}
                    </Button>
                    <Button variant="outline" size="sm" onClick={disconnect} disabled={disconnecting}
                        className="gap-1.5 text-destructive hover:text-destructive">
                        {disconnecting ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Unlink className="h-3.5 w-3.5" />}
                        Desconectar
                    </Button>
                    {testSent && (
                        <span className="flex items-center gap-1 text-sm text-emerald-500">
                            <Check className="h-4 w-4" /> Enviado
                        </span>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-lg border bg-card p-4 space-y-3">
                <div className="flex items-center gap-2">
                    <MessageCircle className="h-5 w-5 text-blue-500" />
                    <span className="font-medium">Vincular Telegram</span>
                </div>
                <p className="text-sm text-muted-foreground">
                    Conectá tu cuenta de Telegram para recibir notificaciones cuando el agente AI tome acciones en tus bots.
                </p>

                {!deepLink && !manualMode ? (
                    <div className="space-y-3">
                        <p className="text-xs text-muted-foreground">
                            Se generará un código único que vincularás enviándolo al bot de Telegram. Esto asegura que solo vos puedas conectar tu cuenta.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={startLink} disabled={linking} size="sm" className="gap-1.5">
                                {linking ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Key className="h-3.5 w-3.5" />}
                                {linking ? "Generando código..." : "Generar código de vinculación"}
                            </Button>
                            <Button onClick={() => setManualMode(true)} variant="ghost" size="sm" className="gap-1.5 text-muted-foreground">
                                Tengo mi Chat ID
                            </Button>
                        </div>
                    </div>
                ) : manualMode ? (
                    <div className="space-y-3 rounded-lg border border-muted bg-muted/30 p-4">
                        <p className="text-sm text-muted-foreground">
                            Ingresá tu Chat ID de Telegram. Podés obtenerlo enviando{" "}
                            <code className="bg-muted px-1 rounded">/start</code> a{" "}
                            <a href="https://t.me/trading_wizardgpt_bot" target="_blank" rel="noopener noreferrer"
                                className="text-blue-500 underline">@trading_wizardgpt_bot</a>.
                        </p>
                        <div className="flex gap-2">
                            <Input
                                value={chatId}
                                onChange={(e) => setChatId(e.target.value)}
                                placeholder="Ej: 123456789"
                                className="max-w-xs"
                            />
                            <Button onClick={saveManualChatId} disabled={savingManual || !chatId.trim()} size="sm">
                                {savingManual ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : "Guardar"}
                            </Button>
                        </div>
                        <button onClick={() => setManualMode(false)} className="text-xs text-muted-foreground underline">
                            Volver
                        </button>
                    </div>
                ) : (
                    <div className="space-y-4 rounded-lg border border-blue-500/20 bg-blue-500/10 p-4">
                        {/* Code display */}
                        <div className="space-y-1.5">
                            <p className="text-xs font-medium uppercase tracking-wider text-blue-400">
                                Tu código de vinculación
                            </p>
                            <div className="flex items-center gap-2">
                                <code className="rounded bg-muted border px-3 py-1.5 text-sm font-mono font-semibold tracking-wide select-all">
                                    {linkToken}
                                </code>
                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0"
                                    onClick={() => copyCode(linkToken!)}>
                                    {copied
                                        ? <Check className="h-3.5 w-3.5 text-emerald-500" />
                                        : <ClipboardCopy className="h-3.5 w-3.5 text-muted-foreground" />}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Este código es único para tu cuenta y expira al usarse.
                            </p>
                        </div>

                        <hr className="border-blue-500/20" />

                        {/* Steps */}
                        <div className="space-y-3">
                            <div className="flex gap-3">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold">1</span>
                                <div>
                                    <p className="text-sm font-medium text-blue-300">
                                        Abrí el bot en Telegram
                                    </p>
                                    <a href={deepLink!} target="_blank" rel="noopener noreferrer"
                                        onClick={() => startAutoPolling()}
                                        className="inline-flex items-center gap-1.5 mt-1 text-sm font-medium text-blue-400 underline hover:no-underline">
                                        <ExternalLink className="h-3.5 w-3.5" />
                                        Abrir @trading_wizardgpt_bot
                                    </a>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        El link ya incluye tu código. Al tocar "Iniciar" se envía automáticamente.
                                    </p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold">2</span>
                                <div>
                                    <p className="text-sm font-medium text-blue-300">
                                        Tocá "Iniciar" en Telegram
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-0.5">
                                        O enviá manualmente: <code className="bg-muted border px-1.5 py-0.5 rounded text-xs cursor-pointer hover:bg-muted/80"
                                        onClick={() => copyCode(`/start ${linkToken}`)}>/start {linkToken?.slice(0, 8)}...</code>
                                    </p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold">3</span>
                                <div>
                                    <p className="text-sm font-medium text-blue-300">
                                        Verificá la conexión
                                    </p>
                                    {autoPolling ? (
                                        <div className="flex items-center gap-2 mt-1.5">
                                            <Loader2 className="h-3.5 w-3.5 animate-spin text-blue-400" />
                                            <span className="text-xs text-blue-400">Esperando vinculación...</span>
                                            <button onClick={stopAutoPolling} className="text-xs text-muted-foreground underline ml-1">
                                                Cancelar
                                            </button>
                                        </div>
                                    ) : (
                                        <Button onClick={pollOnce} size="sm" disabled={polling} className="gap-1.5 mt-1.5">
                                            {polling ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                                            {polling ? "Verificando..." : "Verificar conexión"}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <hr className="border-blue-500/20" />

                        <div className="flex flex-wrap gap-3 text-xs">
                            <button onClick={() => { stopAutoPolling(); setDeepLink(null); setManualMode(true); }}
                                className="text-muted-foreground underline">
                                Ingresar Chat ID manualmente
                            </button>
                            <button onClick={() => { stopAutoPolling(); setDeepLink(null); setLinkToken(null); }}
                                className="text-muted-foreground underline">
                                Cancelar
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
