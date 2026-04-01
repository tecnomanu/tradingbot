import { Component, ErrorInfo, ReactNode } from "react";
import { AlertTriangle, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";

interface Props {
    children: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error("[ErrorBoundary]", error, info.componentStack);
    }

    handleReload = () => {
        window.location.reload();
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-foreground">
                    <div className="flex flex-col items-center gap-6 text-center max-w-md">
                        <div className="flex items-center justify-center w-16 h-16 rounded-full bg-muted">
                            <AlertTriangle className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold mb-2">Algo salió mal</h1>
                            <p className="text-sm text-muted-foreground">
                                Ocurrió un error inesperado en la aplicación.
                            </p>
                        </div>
                        <Button onClick={this.handleReload}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Recargar página
                        </Button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
