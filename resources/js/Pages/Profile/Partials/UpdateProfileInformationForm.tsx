import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Link, useForm, usePage } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler } from "react";

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route("profile.update"));
    };

    return (
        <section>
            <form onSubmit={submit} className="space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="name">Nombre</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        required
                        autoComplete="name"
                    />
                    {errors.name && (
                        <p className="text-sm text-destructive">
                            {errors.name}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        required
                        autoComplete="username"
                    />
                    {errors.email && (
                        <p className="text-sm text-destructive">
                            {errors.email}
                        </p>
                    )}
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-foreground">
                            Tu dirección de correo electrónico no está
                            verificada.
                            <Link
                                href={route("verification.send")}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-muted-foreground underline hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Hacé clic acá para reenviar el correo de
                                verificación.
                            </Link>
                        </p>

                        {status === "verification-link-sent" && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Se ha enviado un nuevo enlace de verificación a
                                tu dirección de correo electrónico.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />{" "}
                                Guardando...
                            </>
                        ) : (
                            "Guardar"
                        )}
                    </Button>

                    {recentlySuccessful && (
                        <p className="text-sm text-muted-foreground animate-fade-in transition ease-in-out">
                            Guardado.
                        </p>
                    )}
                </div>
            </form>
        </section>
    );
}
