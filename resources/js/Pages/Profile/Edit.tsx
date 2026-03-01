import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Head } from "@inertiajs/react";
import { KeyRound, Trash2, User } from "lucide-react";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Perfil
                </h2>
            }
        >
            <Head title="Perfil" />

            <div className="py-8 text-foreground">
                <div className="mx-auto max-w-2xl px-4 sm:px-6">
                    <Tabs defaultValue="profile" className="space-y-6">
                        <TabsList className="grid w-full grid-cols-3">
                            <TabsTrigger
                                value="profile"
                                className="flex items-center gap-2"
                            >
                                <User className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">Perfil</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="password"
                                className="flex items-center gap-2"
                            >
                                <KeyRound className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">
                                    Contraseña
                                </span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="danger"
                                className="flex items-center gap-2"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">
                                    Eliminar
                                </span>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="profile" className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    Información del Perfil
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Actualizá el nombre de tu cuenta y dirección
                                    de email.
                                </p>
                            </div>
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                            />
                        </TabsContent>

                        <TabsContent value="password" className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    Actualizar Contraseña
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Asegurate de usar una contraseña larga y
                                    aleatoria.
                                </p>
                            </div>
                            <UpdatePasswordForm />
                        </TabsContent>

                        <TabsContent value="danger" className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    Eliminar Cuenta
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Una vez eliminada, todos tus datos serán
                                    eliminados permanentemente.
                                </p>
                            </div>
                            <DeleteUserForm />
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
