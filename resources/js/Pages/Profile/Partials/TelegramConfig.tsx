import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import axios from "axios";
import { Check, ExternalLink, Link2, Loader2, MessageCircle, Send, Unlink } from "lucide-react";
import { useCallback, useState } from "react";

interface Props {
    chatId: string | null;
    connected: boolean;
}

export default function TelegramConfig({ chatId: initialChatId, connected: initialConnected }: Props) {
    const [connected, setConnected] = useState(initialConnected);
    const [chatId, setChatId] = useState(initialChatId ?? "");
    const [linking, setLinking] = useState(false);
    const [deepLink, setDeepLink] = useState<string | null>(null);
    const [polling, setPolling] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testSent, setTestSent] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);
    const [manualMode, setManualMode] = useState(false);
    const [savingManual, setSavingManual] = useState(false);

    const startLink = useCallback(async () => {
        setLinking(true);
        try {
            const { data } = await axios.post(route("telegram.link-token"));
            setDeepLink(data.deep_link);
        } catch {
            alert("Error generando token de vinculación.");
        } finally {
            setLinking(false);
        }
    }, []);

    const pollForConnection = useCallback(async () => {
        setPolling(true);
        try {
            const { data } = await axios.post(route("telegram.poll"));
            if (data.connected) {
                setConnected(true);
                setChatId(data.chat_id);
                setDeepLink(null);
            } else {
                alert("Aún no detectado. Asegurate de haber presionado 'Iniciar' en Telegram y volvé a intentar.");
            }
        } catch {
            alert("Error verificando conexión.");
        } finally {
            setPolling(false);
        }
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
        } catch {
            alert("Error desconectando.");
        } finally {
            setDisconnecting(false);
        }
    }, []);

    if (connected) {
        return (
            <div className="space-y-4">
                <div className="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 p-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <Check className="h-5 w-5 text-emerald-500" />
                        <span className="font-medium text-emerald-700 dark:text-emerald-300">
                            Telegram conectado
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Chat ID: <code className="bg-muted px-1.5 py-0.5 rounded text-xs">{chatId}</code>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Activá las notificaciones en cada bot desde su pestaña AI Agent.
                    </p>
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
                    Conectá tu cuenta de Telegram para recibir notificaciones del agente AI.
                </p>

                {!deepLink && !manualMode ? (
                    <div className="flex flex-wrap gap-2">
                        <Button onClick={startLink} disabled={linking} size="sm" className="gap-1.5">
                            {linking ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Link2 className="h-3.5 w-3.5" />}
                            {linking ? "Generando..." : "Vincular automático"}
                        </Button>
                        <Button onClick={() => setManualMode(true)} variant="outline" size="sm" className="gap-1.5">
                            Ingresar Chat ID manual
                        </Button>
                    </div>
                ) : manualMode ? (
                    <div className="space-y-3 rounded-lg border border-muted bg-muted/30 p-4">
                        <p className="text-sm text-muted-foreground">
                            Enviá <code className="bg-muted px-1 rounded">/start</code> a{" "}
                            <a href="https://t.me/trading_wizardgpt_bot" target="_blank" rel="noopener noreferrer"
                                className="text-blue-500 underline">@trading_wizardgpt_bot</a>{" "}
                            y copiá el Chat ID que te devuelve.
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
                    <div className="space-y-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-4">
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            1. Hacé click para abrir el bot en Telegram:
                        </p>
                        <a href={deepLink!} target="_blank" rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 underline hover:no-underline">
                            <ExternalLink className="h-3.5 w-3.5" />
                            Abrir @trading_wizardgpt_bot
                        </a>
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            2. Presioná "Iniciar" en Telegram
                        </p>
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            3. Volvé acá y verificá:
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={pollForConnection} size="sm" disabled={polling} className="gap-1.5">
                                {polling ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
                                {polling ? "Verificando..." : "Verificar conexión"}
                            </Button>
                            <button onClick={() => { setDeepLink(null); setManualMode(true); }}
                                className="text-xs text-muted-foreground underline">
                                Ingresar manualmente
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
