import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { BinanceAccount } from "@/types/bot";
import { Head, useForm, router } from "@inertiajs/react";
import axios from "axios";
import { CheckCircle, DollarSign, Key, Loader2, Plus, Trash2, Wifi, XCircle } from "lucide-react";
import { useState } from "react";

interface BinanceAccountsProps {
    accounts: BinanceAccount[];
}

export default function Index({ accounts }: BinanceAccountsProps) {
    const [testing, setTesting] = useState<number | null>(null);
    const [testResult, setTestResult] = useState<
        Record<number, { success: boolean; message: string }>
    >({});
    const [showForm, setShowForm] = useState(false);
    const [balanceLoading, setBalanceLoading] = useState<number | null>(null);
    const [balances, setBalances] = useState<
        Record<number, { available_usdt: number; total_usdt: number }>
    >({});

    const { data, setData, post, processing, errors, reset } = useForm({
        label: "",
        api_key: "",
        api_secret: "",
        is_testnet: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("binance-accounts.store"), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    };

    const handleTest = async (accountId: number) => {
        setTesting(accountId);
        try {
            const res = await axios.post(
                route("binance-accounts.test", accountId)
            );
            setTestResult((prev) => ({
                ...prev,
                [accountId]: {
                    success: res.data.success,
                    message: res.data.message ?? "Conexión exitosa",
                },
            }));
            if (res.data.success) {
                handleFetchBalance(accountId);
            }
        } catch (err: unknown) {
            const message =
                (err as { response?: { data?: { message?: string } } })
                    ?.response?.data?.message || "Error de conexión";
            setTestResult((prev) => ({
                ...prev,
                [accountId]: { success: false, message },
            }));
        } finally {
            setTesting(null);
        }
    };

    const handleFetchBalance = async (accountId: number) => {
        setBalanceLoading(accountId);
        try {
            const res = await axios.get(
                route("binance-accounts.balance", accountId)
            );
            const d = res.data?.data ?? res.data;
            setBalances((prev) => ({
                ...prev,
                [accountId]: {
                    available_usdt: d?.available_usdt ?? 0,
                    total_usdt: d?.total_usdt ?? 0,
                },
            }));
        } catch {
            setBalances((prev) => ({
                ...prev,
                [accountId]: { available_usdt: 0, total_usdt: 0 },
            }));
        } finally {
            setBalanceLoading(null);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Cuentas Binance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Configurá tus API Keys para operar
                        </p>
                    </div>
                    <Button onClick={() => setShowForm(!showForm)}>
                        <Plus className="mr-2 h-4 w-4" /> Agregar Cuenta
                    </Button>
                </div>
            }
        >
            <Head title="Cuentas Binance" />

            {showForm && (
                <Card className="mb-6 animate-fade-in">
                    <CardHeader>
                        <CardTitle>Nueva Cuenta de Binance</CardTitle>
                        <CardDescription>
                            Las API keys se encriptan antes de almacenarse
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            id="account-form"
                            onSubmit={handleSubmit}
                            className="space-y-4"
                        >
                            <div className="space-y-2">
                                <Label>Nombre / Etiqueta</Label>
                                <Input
                                    value={data.label}
                                    onChange={(e) =>
                                        setData("label", e.target.value)
                                    }
                                    placeholder="Ej: Cuenta Principal"
                                />
                                {errors.label && (
                                    <p className="text-sm text-destructive">
                                        {errors.label}
                                    </p>
                                )}
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>API Key</Label>
                                    <Input
                                        type="password"
                                        className="font-mono"
                                        value={data.api_key}
                                        onChange={(e) =>
                                            setData("api_key", e.target.value)
                                        }
                                        placeholder="Tu API Key"
                                    />
                                    {errors.api_key && (
                                        <p className="text-sm text-destructive">
                                            {errors.api_key}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label>API Secret</Label>
                                    <Input
                                        type="password"
                                        className="font-mono"
                                        value={data.api_secret}
                                        onChange={(e) =>
                                            setData(
                                                "api_secret",
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Tu API Secret"
                                    />
                                    {errors.api_secret && (
                                        <p className="text-sm text-destructive">
                                            {errors.api_secret}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 mt-4">
                                <Checkbox
                                    id="is_testnet"
                                    checked={data.is_testnet}
                                    onCheckedChange={(checked) =>
                                        setData("is_testnet", checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="is_testnet"
                                    className="text-sm font-normal"
                                >
                                    Usar Testnet (modo prueba)
                                </Label>
                            </div>
                        </form>
                    </CardContent>
                    <CardFooter className="flex justify-between">
                        <Button
                            variant="outline"
                            onClick={() => setShowForm(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            form="account-form"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />{" "}
                                    Guardando...
                                </>
                            ) : (
                                "Guardar Cuenta"
                            )}
                        </Button>
                    </CardFooter>
                </Card>
            )}

            {accounts.length === 0 && !showForm ? (
                <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 px-6 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/10 mb-4">
                        <Key className="h-8 w-8 text-amber-500" />
                    </div>
                    <h1 className="text-lg font-semibold">
                        Sin cuentas configuradas
                    </h1>
                    <p className="mt-2 max-w-md text-sm text-muted-foreground">
                        Para operar con los grid bots necesitás una API Key de
                        Binance Futures. Creala en{" "}
                        <a
                            href="https://www.binance.com/es/my/settings/api-management"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-amber-500 underline hover:no-underline"
                        >
                            Binance → API Management
                        </a>
                        . Activá permisos de lectura y trading de Futuros.
                    </p>
                    <Button
                        onClick={() => setShowForm(true)}
                        className="mt-6"
                        size="lg"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Agregar cuenta
                    </Button>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {accounts.map((account) => (
                        <Card key={account.id} className="animate-fade-in">
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10">
                                            <Key className="h-4 w-4 text-amber-500" />
                                        </div>
                                        <CardTitle className="text-base">
                                            {account.label}
                                        </CardTitle>
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        {account.is_testnet && (
                                            <Badge
                                                variant="secondary"
                                                className="bg-muted text-muted-foreground"
                                            >
                                                Testnet
                                            </Badge>
                                        )}
                                        {account.is_active ? (
                                            <Badge
                                                variant="default"
                                                className="bg-emerald-600 hover:bg-emerald-600"
                                            >
                                                Activa
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="border-muted-foreground/30 text-muted-foreground"
                                            >
                                                Inactiva
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        API Key
                                    </span>
                                    <span className="font-mono text-xs">
                                        {account.masked_api_key}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Bots
                                    </span>
                                    <span>{account.bots_count}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Última conexión
                                    </span>
                                    <span>
                                        {account.last_connected_at || "Nunca"}
                                    </span>
                                </div>

                                {(balances[account.id] ||
                                    balanceLoading === account.id) && (
                                    <div className="flex items-center justify-between rounded-lg bg-muted/50 p-2">
                                        <span className="text-muted-foreground">
                                            Balance USDT
                                        </span>
                                        {balanceLoading === account.id ? (
                                            <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                                        ) : (
                                            <span className="font-medium tabular-nums">
                                                {balances[account.id]
                                                    ?.available_usdt?.toLocaleString(
                                                        "es-AR",
                                                        {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        }
                                                    ) ?? "—"}{" "}
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    disp.
                                                </span>
                                            </span>
                                        )}
                                    </div>
                                )}

                                {testResult[account.id] && (
                                    <div
                                        className={`mt-2 flex items-center gap-2 rounded-lg p-2 text-xs ${testResult[account.id].success ? "bg-green-500/10 text-green-500" : "bg-destructive/10 text-destructive"}`}
                                    >
                                        {testResult[account.id].success ? (
                                            <CheckCircle className="h-3 w-3" />
                                        ) : (
                                            <XCircle className="h-3 w-3" />
                                        )}
                                        {testResult[account.id].message}
                                    </div>
                                )}
                            </CardContent>
                            <CardFooter className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="flex-1 min-w-[80px]"
                                    onClick={() => handleTest(account.id)}
                                    disabled={testing === account.id}
                                >
                                    {testing === account.id ? (
                                        <>
                                            <Loader2 className="mr-2 h-3 w-3 animate-spin" />{" "}
                                            Probando...
                                        </>
                                    ) : (
                                        <>
                                            <Wifi className="mr-2 h-3 w-3" />{" "}
                                            Probar conexión
                                        </>
                                    )}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="flex-1 min-w-[80px]"
                                    onClick={() =>
                                        handleFetchBalance(account.id)
                                    }
                                    disabled={balanceLoading === account.id}
                                >
                                    {balanceLoading === account.id ? (
                                        <Loader2 className="h-3 w-3 animate-spin" />
                                    ) : (
                                        <>
                                            <DollarSign className="mr-2 h-3 w-3" />{" "}
                                            Ver balance
                                        </>
                                    )}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => {
                                        if (
                                            confirm(
                                                "¿Eliminar esta cuenta?",
                                            )
                                        )
                                            router.delete(
                                                route(
                                                    "binance-accounts.destroy",
                                                    account.id
                                                )
                                            );
                                    }}
                                >
                                    <Trash2 className="h-3 w-3" />
                                </Button>
                            </CardFooter>
                        </Card>
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
