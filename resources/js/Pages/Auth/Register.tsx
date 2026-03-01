import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Head, Link, useForm } from "@inertiajs/react";
import { BarChart3, Bot, Loader2, Shield, TrendingUp } from "lucide-react";
import { FormEventHandler } from "react";

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("register"), {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <>
            <Head title="Registrarse" />
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
                                Empezá a operar
                                <br />
                                en minutos
                            </h1>
                            <p className="text-muted-foreground leading-relaxed">
                                Creá tu cuenta, conectá tu API de Binance y
                                lanzá tu primer bot de grid trading.
                            </p>
                        </div>

                        <div className="space-y-4 pt-4">
                            <Feature
                                icon={Bot}
                                title="Configuración simple"
                                desc="Creá un bot en menos de 2 minutos"
                            />
                            <Feature
                                icon={BarChart3}
                                title="Testnet disponible"
                                desc="Probá sin riesgo con dinero virtual"
                            />
                            <Feature
                                icon={Shield}
                                title="Datos encriptados"
                                desc="Seguridad de nivel empresarial"
                            />
                        </div>
                    </div>
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

                        <Card>
                            <CardHeader className="text-center">
                                <CardTitle className="text-xl">
                                    Crear cuenta
                                </CardTitle>
                                <CardDescription>
                                    Ingresá tus datos para registrarte
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submit}>
                                    <div className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Nombre</Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) =>
                                                    setData(
                                                        "name",
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                autoFocus
                                                placeholder="Tu nombre"
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-destructive">
                                                    {errors.name}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) =>
                                                    setData(
                                                        "email",
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                placeholder="tu@email.com"
                                            />
                                            {errors.email && (
                                                <p className="text-sm text-destructive">
                                                    {errors.email}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Contraseña
                                            </Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                value={data.password}
                                                onChange={(e) =>
                                                    setData(
                                                        "password",
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {errors.password && (
                                                <p className="text-sm text-destructive">
                                                    {errors.password}
                                                </p>
                                            )}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">
                                                Confirmar contraseña
                                            </Label>
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                value={
                                                    data.password_confirmation
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        "password_confirmation",
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {errors.password_confirmation && (
                                                <p className="text-sm text-destructive">
                                                    {errors.password_confirmation}
                                                </p>
                                            )}
                                        </div>

                                        <Button
                                            type="submit"
                                            className="w-full mt-2"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Registrando...
                                                </>
                                            ) : (
                                                "Registrarse"
                                            )}
                                        </Button>
                                    </div>
                                    <div className="mt-4 text-center text-sm">
                                        ¿Ya tenés cuenta?{" "}
                                        <Link
                                            href={route("login")}
                                            className="underline underline-offset-4"
                                        >
                                            Iniciar sesión
                                        </Link>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
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
