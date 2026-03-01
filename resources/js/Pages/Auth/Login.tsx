import { Head } from "@inertiajs/react";
import { BarChart3, Bot, Shield, TrendingUp } from "lucide-react";
import { LoginForm } from "./components/LoginForm";

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    return (
        <>
            <Head title="Iniciar sesión" />
            <div className="flex min-h-svh bg-background text-foreground">
                {/* Left: Branding */}
                <div className="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-primary/20 via-background to-background items-center justify-center p-12">
                    <div className="max-w-md space-y-8">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <TrendingUp className="h-5 w-5" />
                            </div>
                            <span className="text-2xl font-bold tracking-tight">
                                GridBot
                            </span>
                        </div>

                        <div className="space-y-3">
                            <h1 className="text-3xl font-bold tracking-tight">
                                Trading automatizado
                                <br />
                                de Grid Bots
                            </h1>
                            <p className="text-muted-foreground leading-relaxed">
                                Operá en Binance Futures con bots de rejilla
                                inteligentes. Configurá tu estrategia, definí
                                el rango y dejá que el bot opere por vos.
                            </p>
                        </div>

                        <div className="space-y-4 pt-4">
                            <Feature
                                icon={Bot}
                                title="Grid Bots automatizados"
                                desc="Creá bots que operan 24/7 en futuros"
                            />
                            <Feature
                                icon={BarChart3}
                                title="Datos en tiempo real"
                                desc="Charts, order book y precios en vivo"
                            />
                            <Feature
                                icon={Shield}
                                title="API Keys encriptadas"
                                desc="Tus credenciales siempre seguras"
                            />
                        </div>
                    </div>

                    <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-border to-transparent" />
                </div>

                {/* Right: Form */}
                <div className="flex w-full lg:w-1/2 flex-col items-center justify-center p-6 md:p-10">
                    <div className="flex w-full max-w-sm flex-col gap-6">
                        <a
                            href="/"
                            className="flex items-center gap-2 self-center font-semibold lg:hidden"
                        >
                            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <TrendingUp className="size-4" />
                            </div>
                            GridBot Trading
                        </a>
                        <LoginForm
                            status={status}
                            canResetPassword={canResetPassword}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

function Feature({
    icon: Icon,
    title,
    desc,
}: {
    icon: any;
    title: string;
    desc: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                <Icon className="h-4 w-4 text-primary" />
            </div>
            <div>
                <p className="text-sm font-medium">{title}</p>
                <p className="text-xs text-muted-foreground">{desc}</p>
            </div>
        </div>
    );
}
