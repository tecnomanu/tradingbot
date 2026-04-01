import { Button } from "@/components/ui/button";
import { Head, Link } from "@inertiajs/react";
import { Home, AlertTriangle } from "lucide-react";

interface Props {
    status: number;
}

const messages: Record<number, { title: string; description: string }> = {
    404: {
        title: "Página no encontrada",
        description: "La página que buscás no existe o fue movida.",
    },
    403: {
        title: "Acceso denegado",
        description: "No tenés permiso para acceder a esta sección.",
    },
    500: {
        title: "Error del servidor",
        description: "Ocurrió un error interno. Por favor intentá nuevamente.",
    },
    503: {
        title: "Servicio no disponible",
        description: "El servicio está temporalmente fuera de línea.",
    },
};

export default function Error({ status }: Props) {
    const { title, description } = messages[status] ?? {
        title: "Error inesperado",
        description: "Ocurrió un error inesperado.",
    };

    return (
        <>
            <Head title={`${status} — ${title}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-foreground">
                <div className="flex flex-col items-center gap-6 text-center max-w-md">
                    <div className="flex items-center justify-center w-16 h-16 rounded-full bg-muted">
                        <AlertTriangle className="h-8 w-8 text-muted-foreground" />
                    </div>
                    <div>
                        <p className="text-6xl font-bold text-muted-foreground mb-2">{status}</p>
                        <h1 className="text-2xl font-semibold mb-2">{title}</h1>
                        <p className="text-muted-foreground">{description}</p>
                    </div>
                    <div className="flex gap-3">
                        <Button asChild>
                            <Link href="/dashboard">
                                <Home className="mr-2 h-4 w-4" />
                                Ir al inicio
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={() => window.history.back()}>
                            Volver
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
