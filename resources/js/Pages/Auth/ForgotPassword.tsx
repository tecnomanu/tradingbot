import GuestLayout from "@/Layouts/GuestLayout";
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
import { Head, useForm } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler } from "react";

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("password.email"));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar contraseña" />
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">
                        Recuperar contraseña
                    </CardTitle>
                    <CardDescription>
                        Ingresá tu email y te enviaremos un link para
                        restablecer tu contraseña.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {status && (
                        <div className="mb-4 text-sm font-medium text-primary text-center">
                            {status}
                        </div>
                    )}
                    <form onSubmit={submit}>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    required
                                    autoFocus
                                    placeholder="tu@email.com"
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">
                                        {errors.email}
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
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />{" "}
                                        Enviando...
                                    </>
                                ) : (
                                    "Enviar link"
                                )}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
