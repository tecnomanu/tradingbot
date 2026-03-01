import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Link, useForm } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler } from "react";

interface LoginFormProps extends React.ComponentPropsWithoutRef<"div"> {
    status?: string;
    canResetPassword?: boolean;
}

export function LoginForm({
    className,
    status,
    canResetPassword = true,
    ...props
}: LoginFormProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("login"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">Bienvenido</CardTitle>
                    <CardDescription>
                        Ingresá a tu cuenta para gestionar tus bots
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {status && (
                        <div className="mb-4 text-sm font-medium text-primary">
                            {status}
                        </div>
                    )}
                    <form onSubmit={submit}>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="tu@email.com"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Contraseña</Label>
                                    {canResetPassword && (
                                        <Link
                                            href={route("password.request")}
                                            className="ml-auto text-sm underline-offset-4 hover:underline text-muted-foreground"
                                        >
                                            ¿Olvidaste tu contraseña?
                                        </Link>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData("password", e.target.value)
                                    }
                                    required
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="remember"
                                    checked={data.remember}
                                    onCheckedChange={(checked) =>
                                        setData("remember", checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-sm font-normal"
                                >
                                    Recordarme
                                </Label>
                            </div>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Ingresando...
                                    </>
                                ) : (
                                    "Ingresar"
                                )}
                            </Button>
                        </div>
                        <div className="mt-4 text-center text-sm">
                            ¿No tenés cuenta?{" "}
                            <Link
                                href={route("register")}
                                className="underline underline-offset-4"
                            >
                                Registrate
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
