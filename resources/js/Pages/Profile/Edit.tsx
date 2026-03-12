import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Head } from "@inertiajs/react";
import { KeyRound, MessageCircle, Terminal, Trash2, User } from "lucide-react";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";
import ApiKeyManager from "./Partials/ApiKeyManager";
import TelegramConfig from "./Partials/TelegramConfig";

export default function Edit({
    mustVerifyEmail,
    status,
    apiKey,
    telegramChatId,
    telegramConnected,
}: PageProps<{
    mustVerifyEmail: boolean;
    status?: string;
    apiKey: string;
    telegramChatId: string | null;
    telegramConnected: boolean;
}>) {
    const successMessage =
        status === "profile-updated"
            ? "Perfil actualizado correctamente."
            : status === "password-updated"
              ? "Contraseña actualizada correctamente."
              : status === "verification-link-sent"
                ? "Se ha enviado un nuevo enlace de verificación."
                : null;
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Perfil
                </h2>
            }
        >
            <Head title="Perfil" />

            {successMessage && (
                <div className="mx-auto max-w-2xl px-4 sm:px-6 mb-4">
                    <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-600 dark:text-emerald-400">
                        {successMessage}
                    </div>
                </div>
            )}

            <div className="py-8 text-foreground">
                <div className="mx-auto max-w-2xl px-4 sm:px-6">
                    <Tabs defaultValue="profile" className="space-y-6">
                        <TabsList className="grid w-full grid-cols-5">
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
                                value="telegram"
                                className="flex items-center gap-2"
                            >
                                <MessageCircle className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">Telegram</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="apikey"
                                className="flex items-center gap-2"
                            >
                                <Terminal className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">API Key</span>
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

                        <TabsContent value="telegram" className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    Notificaciones Telegram
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Conectá Telegram para recibir alertas cuando
                                    el agente AI ejecute acciones en tus bots.
                                </p>
                            </div>
                            <TelegramConfig
                                chatId={telegramChatId}
                                connected={telegramConnected}
                            />
                        </TabsContent>

                        <TabsContent value="apikey" className="space-y-4">
                            <div>
                                <h3 className="text-lg font-semibold">
                                    API Key Externa
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Usá esta key para conectar agentes o MCP al
                                    bot desde afuera. Cada usuario tiene su propia key.
                                </p>
                            </div>
                            <ApiKeyManager apiKey={apiKey} />
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
