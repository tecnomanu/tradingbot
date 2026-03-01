import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useForm } from "@inertiajs/react";
import { Loader2 } from "lucide-react";
import { FormEventHandler, useRef, useState } from "react";

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: "",
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route("profile.destroy"), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6`}>
            <p className="mt-1 text-sm text-muted-foreground">
                Una vez eliminada la cuenta, todos sus recursos y datos serán
                eliminados permanentemente. Antes de eliminar su cuenta, por
                favor descargue cualquier dato o información que desee
                conservar.
            </p>

            <Dialog
                open={confirmingUserDeletion}
                onOpenChange={(v) => !v && closeModal()}
            >
                <DialogTrigger asChild>
                    <Button variant="destructive" onClick={confirmUserDeletion}>
                        Eliminar Cuenta
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <form onSubmit={deleteUser}>
                        <DialogHeader>
                            <DialogTitle>
                                ¿Estás seguro de que deseas eliminar tu cuenta?
                            </DialogTitle>
                            <DialogDescription>
                                Una vez eliminada la cuenta, todos sus recursos
                                y datos serán eliminados permanentemente. Por
                                favor, ingrese su contraseña para confirmar que
                                desea eliminar la cuenta permanentemente.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="mt-6 space-y-2">
                            <Label htmlFor="password" className="sr-only">
                                Contraseña
                            </Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(e) =>
                                    setData("password", e.target.value)
                                }
                                placeholder="Contraseña"
                                autoFocus
                            />
                            {errors.password && (
                                <p className="text-sm text-destructive">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="mt-6 flex justify-end gap-2">
                            <Button
                                variant="outline"
                                type="button"
                                onClick={closeModal}
                            >
                                Cancelar
                            </Button>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />{" "}
                                        Eliminando...
                                    </>
                                ) : (
                                    "Eliminar Cuenta"
                                )}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
