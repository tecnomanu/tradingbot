import { Button } from "@/components/ui/button";
import axios from "axios";
import { Check, ExternalLink, Link2, Loader2, MessageCircle, Send, Unlink } from "lucide-react";
import { useCallback, useState } from "react";

interface Props {
    chatId: string | null;
    connected: boolean;
}

export default function TelegramConfig({ chatId: initialChatId, connected: initialConnected }: Props) {
    const [connected, setConnected] = useState(initialConnected);
    const [chatId, setChatId] = useState(initialChatId);
    const [linking, setLinking] = useState(false);
    const [deepLink, setDeepLink] = useState<string | null>(null);
    const [testing, setTesting] = useState(false);
    const [testSent, setTestSent] = useState(false);
    const [disconnecting, setDisconnecting] = useState(false);

    const startLink = useCallback(async () => {
        setLinking(true);
        try {
            const { data } = await axios.post(route("telegram.link-token"));
            setDeepLink(data.deep_link);
        } catch {
            alert("Error generando el token de vinculación.");
        } finally {
            setLinking(false);
        }
    }, []);

    const checkConnection = useCallback(async () => {
        window.location.reload();
    }, []);

    const sendTest = useCallback(async () => {
        setTesting(true);
        setTestSent(false);
        try {
            const { data } = await axios.post(route("telegram.test"));
            setTestSent(data.success);
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
            setChatId(null);
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
                        Las notificaciones se envían a este chat cuando un bot con notificaciones activas ejecuta acciones.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={sendTest}
                        disabled={testing}
                        className="gap-1.5"
                    >
                        {testing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Send className="h-3.5 w-3.5" />}
                        {testing ? "Enviando..." : "Enviar prueba"}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={disconnect}
                        disabled={disconnecting}
                        className="gap-1.5 text-destructive hover:text-destructive"
                    >
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

                {!deepLink ? (
                    <Button
                        onClick={startLink}
                        disabled={linking}
                        size="sm"
                        className="gap-1.5"
                    >
                        {linking ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Link2 className="h-3.5 w-3.5" />}
                        {linking ? "Generando..." : "Vincular Telegram"}
                    </Button>
                ) : (
                    <div className="space-y-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-4">
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            Paso 1: Hacé click en el siguiente enlace
                        </p>
                        <a
                            href={deepLink}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 underline hover:no-underline"
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                            Abrir en Telegram
                        </a>
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300 mt-2">
                            Paso 2: Presioná "Iniciar" en el bot de Telegram
                        </p>
                        <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                            Paso 3: Volvé acá y verificá
                        </p>
                        <Button onClick={checkConnection} size="sm" variant="outline" className="gap-1.5 mt-2">
                            <Check className="h-3.5 w-3.5" />
                            Verificar conexión
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}
