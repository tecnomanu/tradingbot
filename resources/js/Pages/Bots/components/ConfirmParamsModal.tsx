import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";
import { GridConfig } from "@/types/bot";
import { formatCurrency, sideLabel } from "@/utils/formatters";
import { AlertTriangle, Loader2, Shield } from "lucide-react";

interface FormData {
    symbol: string;
    side: string;
    leverage: string;
    grid_count: string;
}

interface ConfirmParamsModalProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    processing: boolean;
    config: GridConfig;
    formData: FormData;
    isEditing?: boolean;
    editBotStatus?: string;
}

export default function ConfirmParamsModal({
    open,
    onClose,
    onConfirm,
    processing,
    config,
    formData,
    isEditing = false,
    editBotStatus,
}: ConfirmParamsModalProps) {
    const isActiveBot = isEditing && editBotStatus === "active";

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-primary" />
                        Confirmar Parámetros
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? "Revisá los cambios antes de guardar"
                            : "Revisá los parámetros del bot antes de crearlo"}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    {isActiveBot && (
                        <div className="rounded-lg bg-yellow-500/10 border border-yellow-500/30 p-3 space-y-1.5">
                            <div className="flex items-center gap-2 text-sm font-medium text-yellow-600 dark:text-yellow-400">
                                <AlertTriangle className="h-4 w-4" />
                                Bot activo — se aplicarán los siguientes cambios:
                            </div>
                            <ul className="text-xs text-yellow-600/80 dark:text-yellow-400/80 space-y-0.5 pl-6 list-disc">
                                <li>Se cancelarán todas las órdenes abiertas en Binance</li>
                                <li>Se actualizará la configuración del grid</li>
                                <li>Se colocarán nuevas órdenes con los parámetros actualizados</li>
                            </ul>
                        </div>
                    )}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Inversión real
                            </span>
                            <span className="font-medium">
                                {formatCurrency(config.real_investment)} USDT
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Margen adicional
                            </span>
                            <span className="font-medium">
                                {formatCurrency(config.additional_margin)} USDT
                            </span>
                        </div>
                    </div>
                    <Separator />
                    <div className="space-y-2">
                        {[
                            ["Par", formData.symbol],
                            ["Dirección", sideLabel(formData.side)],
                            ["Apalancamiento", `${formData.leverage}x`],
                            ["Rejillas", formData.grid_count],
                            ["Ganancia/rejilla", `${config.profit_per_grid}%`],
                            [
                                "Comisión/rejilla",
                                `${config.commission_per_grid}%`,
                            ],
                            [
                                "Precio est. liq.",
                                config.est_liquidation_price > 0
                                    ? `${formatCurrency(config.est_liquidation_price)} USDT`
                                    : "N/A",
                            ],
                        ].map(([label, value]) => (
                            <div
                                key={label as string}
                                className="flex items-center justify-between text-sm"
                            >
                                <span className="text-muted-foreground">
                                    {label}
                                </span>
                                <span className="font-medium">{value}</span>
                            </div>
                        ))}
                    </div>
                    {parseFloat(formData.leverage) >= 10 && (
                        <div className="rounded-lg bg-destructive/10 p-3 text-xs text-destructive">
                            <strong>⚠ Alto apalancamiento:</strong> Un
                            apalancamiento de {formData.leverage}x aumenta
                            significativamente el riesgo de liquidación.
                        </div>
                    )}
                </div>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button onClick={onConfirm} disabled={processing}>
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                {isActiveBot
                                    ? "Aplicando cambios..."
                                    : isEditing
                                      ? "Guardando..."
                                      : "Creando..."}
                            </>
                        ) : isActiveBot ? (
                            "Aplicar cambios y reiniciar"
                        ) : isEditing ? (
                            "Guardar cambios"
                        ) : (
                            "Crear Bot"
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
