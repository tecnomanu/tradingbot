import { Button } from "@/components/ui/button";
import axios from "axios";
import { Check, Copy, RefreshCw } from "lucide-react";
import { useCallback, useState } from "react";

interface Props {
    apiKey: string;
}

export default function ApiKeyManager({ apiKey: initialKey }: Props) {
    const [apiKey, setApiKey]         = useState(initialKey);
    const [visible, setVisible]       = useState(false);
    const [copied, setCopied]         = useState(false);
    const [rotating, setRotating]     = useState(false);
    const [justRotated, setJustRotated] = useState(false);

    const maskedKey = apiKey
        ? apiKey.substring(0, 8) + "•".repeat(48) + apiKey.slice(-4)
        : "";

    const copy = useCallback(async () => {
        await navigator.clipboard.writeText(apiKey);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }, [apiKey]);

    const rotate = useCallback(async () => {
        if (!confirm("¿Rotar la API key? La key anterior dejará de funcionar inmediatamente.")) return;

        setRotating(true);
        try {
            const { data } = await axios.post(route("profile.rotate-api-key"));
            setApiKey(data.api_key);
            setVisible(true);
            setJustRotated(true);
            setTimeout(() => setJustRotated(false), 5000);
        } catch {
            alert("Error rotando la key. Intentá de nuevo.");
        } finally {
            setRotating(false);
        }
    }, []);

    return (
        <div className="space-y-4">
            <div className="rounded-lg border bg-card p-4 space-y-3">
                <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-muted-foreground">
                        Tu API Key
                    </p>
                    <button
                        onClick={() => setVisible((v) => !v)}
                        className="text-xs text-muted-foreground underline hover:text-foreground"
                    >
                        {visible ? "Ocultar" : "Mostrar"}
                    </button>
                </div>

                <div className="flex items-center gap-2">
                    <code className="flex-1 rounded bg-muted px-3 py-2 text-xs font-mono break-all select-all">
                        {visible ? apiKey : maskedKey}
                    </code>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={copy}
                        title="Copiar key"
                    >
                        {copied ? (
                            <Check className="h-4 w-4 text-green-500" />
                        ) : (
                            <Copy className="h-4 w-4" />
                        )}
                    </Button>
                </div>

                {justRotated && (
                    <p className="text-xs text-green-600 dark:text-green-400 font-medium">
                        ✓ Key rotada. Copiala ahora — no se volverá a mostrar completa.
                    </p>
                )}
            </div>

            <div className="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-4 space-y-3">
                <div>
                    <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                        Rotar API Key
                    </p>
                    <p className="text-xs text-amber-700 dark:text-amber-300 mt-1">
                        Genera una nueva key e invalida la anterior de inmediato.
                        Actualizá tu agente/MCP con la nueva key.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={rotate}
                    disabled={rotating}
                    className="border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900"
                >
                    <RefreshCw className={`mr-2 h-3.5 w-3.5 ${rotating ? "animate-spin" : ""}`} />
                    {rotating ? "Rotando…" : "Rotar Key"}
                </Button>
            </div>

            <div className="rounded-lg border bg-muted/40 p-4 space-y-2">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    Uso
                </p>
                <pre className="text-xs text-muted-foreground overflow-x-auto whitespace-pre-wrap">{`# Header
X-API-Key: <tu-key>

# O como Bearer token
Authorization: Bearer <tu-key>

# Ejemplo
curl -H "X-API-Key: <tu-key>" \\
  ${window.location.origin}/api/v1/status`}</pre>
            </div>
        </div>
    );
}
