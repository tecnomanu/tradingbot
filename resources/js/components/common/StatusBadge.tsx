import { statusBadgeClass, statusLabel } from "@/utils/formatters";

interface StatusBadgeProps {
    status: string;
    pulse?: boolean;
}

export default function StatusBadge({
    status,
    pulse = false,
}: StatusBadgeProps) {
    return (
        <span
            className={`${statusBadgeClass(status)} ${pulse && status === "active" ? "pulse-active" : ""}`}
        >
            {status === "active" && (
                <span className="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-green-500" />
            )}
            {statusLabel(status)}
        </span>
    );
}
