import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Order } from "@/types/bot";
import { formatCurrency, formatDate } from "@/utils/formatters";
import { Head, router } from "@inertiajs/react";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    RotateCcw,
} from "lucide-react";
import { OrdersLayout } from "./OrdersLayout";

interface Filters {
    status: string;
    side: string;
    symbol: string;
    sort: string;
    dir: string;
}

interface OrderHistoryProps {
    orders: {
        data: (Order & {
            bot?: { id: number; name: string; symbol: string; side: string };
        })[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Filters;
    availableSymbols: string[];
}

const STATUS_OPTIONS = [
    { value: "all", label: "Todos" },
    { value: "filled", label: "Ejecutada" },
    { value: "cancelled", label: "Cancelada" },
    { value: "open", label: "Abierta" },
];

const SIDE_OPTIONS = [
    { value: "all", label: "Todos" },
    { value: "buy", label: "Compra" },
    { value: "sell", label: "Venta" },
];

function FilterSelect({
    value,
    options,
    onChange,
    label,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (val: string) => void;
    label: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <span className="text-[11px] text-muted-foreground whitespace-nowrap">
                {label}
            </span>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-7 rounded-md border bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-primary"
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function SortHeader({
    label,
    field,
    currentSort,
    currentDir,
    onSort,
}: {
    label: string;
    field: string;
    currentSort: string;
    currentDir: string;
    onSort: (field: string) => void;
}) {
    const isActive = currentSort === field;
    return (
        <th
            className="pb-2 text-left font-medium cursor-pointer select-none hover:text-foreground transition-colors"
            onClick={() => onSort(field)}
        >
            <span className="inline-flex items-center gap-1">
                {label}
                {isActive ? (
                    currentDir === "asc" ? (
                        <ArrowUp className="h-3 w-3" />
                    ) : (
                        <ArrowDown className="h-3 w-3" />
                    )
                ) : (
                    <ArrowUpDown className="h-3 w-3 opacity-30" />
                )}
            </span>
        </th>
    );
}

export default function OrderHistory({
    orders,
    filters,
    availableSymbols,
}: OrderHistoryProps) {
    const navigate = (params: Partial<Filters>) => {
        const merged = { ...filters, ...params };
        router.get("/orders/history", merged as any, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSort = (field: string) => {
        const newDir =
            filters.sort === field && filters.dir === "desc" ? "asc" : "desc";
        navigate({ sort: field, dir: newDir });
    };

    const handleReset = () => {
        router.get("/orders/history", {}, { preserveState: false });
    };

    const symbolOptions = [
        { value: "all", label: "Todos" },
        ...availableSymbols.map((s) => ({
            value: s,
            label: s.replace("USDT", "/USDT"),
        })),
    ];

    const hasActiveFilters =
        filters.status !== "all" ||
        filters.side !== "all" ||
        filters.symbol !== "all";

    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Historial de Ordenes" />
            <OrdersLayout>
                <div className="p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-sm font-semibold">
                            Historial de Ordenes ({orders.total ?? orders.data.length})
                        </h2>
                    </div>

                    {/* Filters */}
                    <div className="flex flex-wrap items-center gap-3 mb-4 pb-3 border-b">
                        <FilterSelect
                            label="Estado"
                            value={filters.status}
                            options={STATUS_OPTIONS}
                            onChange={(v) => navigate({ status: v })}
                        />
                        <FilterSelect
                            label="Lado"
                            value={filters.side}
                            options={SIDE_OPTIONS}
                            onChange={(v) => navigate({ side: v })}
                        />
                        <FilterSelect
                            label="Par"
                            value={filters.symbol}
                            options={symbolOptions}
                            onChange={(v) => navigate({ symbol: v })}
                        />
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={handleReset}
                            >
                                <RotateCcw className="mr-1 h-3 w-3" />
                                Limpiar
                            </Button>
                        )}
                    </div>

                    {orders.data.length === 0 ? (
                        <div className="flex flex-col items-center py-16 text-sm text-muted-foreground">
                            No hay órdenes con estos filtros.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="pb-2 text-left font-medium">
                                            Par
                                        </th>
                                        <SortHeader
                                            label="Lado"
                                            field="side"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                        <SortHeader
                                            label="Estado"
                                            field="status"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                        <SortHeader
                                            label="Precio"
                                            field="price"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                        <SortHeader
                                            label="Cantidad"
                                            field="quantity"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                        <SortHeader
                                            label="PNL"
                                            field="pnl"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                        <SortHeader
                                            label="Fecha"
                                            field="created_at"
                                            currentSort={filters.sort}
                                            currentDir={filters.dir}
                                            onSort={handleSort}
                                        />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="hover:bg-muted/20"
                                        >
                                            <td className="py-2.5 font-medium">
                                                {order.bot?.symbol?.replace(
                                                    "USDT",
                                                    "/USDT",
                                                ) || "-"}
                                            </td>
                                            <td className="py-2.5">
                                                <Badge
                                                    variant={
                                                        order.side === "buy"
                                                            ? "default"
                                                            : "destructive"
                                                    }
                                                    className="text-[10px]"
                                                >
                                                    {order.side === "buy"
                                                        ? "Compra"
                                                        : "Venta"}
                                                </Badge>
                                            </td>
                                            <td className="py-2.5">
                                                <Badge
                                                    variant={
                                                        order.status ===
                                                        "filled"
                                                            ? "default"
                                                            : order.status ===
                                                                "open"
                                                              ? "outline"
                                                              : "secondary"
                                                    }
                                                    className="text-[10px]"
                                                >
                                                    {order.status === "filled"
                                                        ? "Ejecutada"
                                                        : order.status ===
                                                            "open"
                                                          ? "Abierta"
                                                          : "Cancelada"}
                                                </Badge>
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {formatCurrency(order.price)}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {order.quantity}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {order.pnl !== null &&
                                                order.pnl !== undefined ? (
                                                    <span
                                                        className={
                                                            order.pnl >= 0
                                                                ? "text-green-500"
                                                                : "text-red-500"
                                                        }
                                                    >
                                                        {order.pnl >= 0
                                                            ? "+"
                                                            : ""}
                                                        {formatCurrency(
                                                            order.pnl,
                                                        )}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2.5 text-right text-muted-foreground">
                                                {order.filled_at
                                                    ? formatDate(
                                                          order.filled_at,
                                                      )
                                                    : formatDate(
                                                          order.created_at,
                                                      )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {orders.last_page > 1 && (
                        <div className="flex items-center justify-between mt-4 pt-3 border-t">
                            <span className="text-xs text-muted-foreground">
                                Página {orders.current_page} de{" "}
                                {orders.last_page}
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 text-xs"
                                    disabled={orders.current_page <= 1}
                                    onClick={() =>
                                        navigate({
                                            ...filters,
                                        } as any)
                                    }
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-7 text-xs"
                                    disabled={
                                        orders.current_page >=
                                        orders.last_page
                                    }
                                    onClick={() =>
                                        navigate({
                                            ...filters,
                                        } as any)
                                    }
                                >
                                    Siguiente
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}
