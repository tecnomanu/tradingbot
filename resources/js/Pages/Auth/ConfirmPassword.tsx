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

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("password.confirm"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar contraseña" />
            <Card>
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">
                        Confirmar contraseña
                    </CardTitle>
                    <CardDescription>
                        Esta es un área segura de la aplicación. Por favor,
                        confirmá tu contraseña antes de continuar.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData("password", e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">
                                        {errors.password}
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
                                        Confirmando...
                                    </>
                                ) : (
                                    "Confirmar"
                                )}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
