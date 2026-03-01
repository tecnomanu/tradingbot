import { PropsWithChildren } from "react";

interface ConfirmDialogProps {
    show: boolean;
    title: string;
    onConfirm: () => void;
    onCancel: () => void;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: "default" | "danger";
}

export default function ConfirmDialog({
    show,
    title,
    children,
    onConfirm,
    onCancel,
    confirmLabel = "Aceptar",
    cancelLabel = "Cancelar",
    variant = "default",
}: PropsWithChildren<ConfirmDialogProps>) {
    if (!show) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/60 backdrop-blur-sm"
                onClick={onCancel}
            />

            {/* Dialog */}
            <div className="relative w-full max-w-lg animate-fade-in rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg-card)] p-6 shadow-2xl">
                <h3 className="text-lg font-semibold text-[var(--color-text-primary)] mb-4">
                    {title}
                </h3>

                <div className="text-sm text-[var(--color-text-secondary)] mb-6">
                    {children}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <button onClick={onCancel} className="btn-secondary">
                        {cancelLabel}
                    </button>
                    <button
                        onClick={onConfirm}
                        className={
                            variant === "danger" ? "btn-danger" : "btn-primary"
                        }
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
