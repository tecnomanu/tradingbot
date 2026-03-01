import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useForm } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler, useRef } from "react";

export default function UpdatePasswordForm() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route("password.update"), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset("password", "password_confirmation");
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset("current_password");
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section>
            <form onSubmit={updatePassword} className="space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="current_password">Contraseña actual</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData("current_password", e.target.value)
                        }
                        type="password"
                        autoComplete="current-password"
                    />
                    {errors.current_password && (
                        <p className="text-sm text-destructive">
                            {errors.current_password}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password">Nueva contraseña</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                        type="password"
                        autoComplete="new-password"
                    />
                    {errors.password && (
                        <p className="text-sm text-destructive">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="password_confirmation">
                        Confirmar contraseña
                    </Label>
                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                        type="password"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && (
                        <p className="text-sm text-destructive">
                            {errors.password_confirmation}
                        </p>
                    )}
                </div>

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
