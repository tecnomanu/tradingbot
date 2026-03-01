import GuestLayout from "@/Layouts/GuestLayout";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Head, Link, useForm } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler } from "react";

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("verification.send"));
    };

    return (
        <GuestLayout>
            <Head title="Verificar Email" />
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">Verificar Email</CardTitle>
                    <CardDescription>
                        ¡Gracias por registrarte! Antes de empezar, ¿podrías
                        verificar tu dirección de correo electrónico haciendo
                        clic en el enlace que te acabamos de enviar? Si no
                        recibiste el correo electrónico, con gusto te enviaremos
                        otro.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {status === "verification-link-sent" && (
                        <div className="mb-4 text-sm font-medium text-green-600">
                            Se ha enviado un nuevo enlace de verificación a la
                            dirección de correo electrónico que proporcionaste
                            durante el registro.
                        </div>
                    )}

                    <form onSubmit={submit} className="flex flex-col space-y-4">
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />{" "}
                                    Enviando...
                                </>
                            ) : (
                                "Reenviar correo de verificación"
                            )}
                        </Button>

                        <div className="text-center mt-2">
                            <Link
                                href={route("logout")}
                                method="post"
                                as="button"
                                className="text-sm text-muted-foreground underline hover:text-foreground focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                            >
                                Cerrar sesión
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
