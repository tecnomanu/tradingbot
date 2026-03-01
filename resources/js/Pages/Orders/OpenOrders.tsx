import { Badge } from "@/components/ui/badge";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Order } from "@/types/bot";
import { formatCurrency, formatDate } from "@/utils/formatters";
import { Head } from "@inertiajs/react";
import { OrdersLayout } from "./OrdersLayout";

interface OpenOrdersProps {
    orders: {
        data: (Order & { bot?: { id: number; name: string; symbol: string; side: string } })[];
        current_page: number;
        last_page: number;
    };
}

export default function OpenOrders({ orders }: OpenOrdersProps) {
    return (
        <AuthenticatedLayout fullWidth>
            <Head title="Ordenes Abiertas" />
            <OrdersLayout>
                <div className="p-5">
                    <h2 className="text-sm font-semibold mb-4">
                        Ordenes Abiertas ({orders.data.length})
                    </h2>

                    {orders.data.length === 0 ? (
                        <div className="flex flex-col items-center py-16 text-sm text-muted-foreground">
                            No hay órdenes abiertas.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="pb-2 text-left font-medium">Par</th>
                                        <th className="pb-2 text-left font-medium">Lado</th>
                                        <th className="pb-2 text-right font-medium">Precio</th>
                                        <th className="pb-2 text-right font-medium">Cantidad</th>
                                        <th className="pb-2 text-right font-medium">Nivel</th>
                                        <th className="pb-2 text-right font-medium">Creada</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {orders.data.map((order) => (
                                        <tr key={order.id} className="hover:bg-muted/20">
                                            <td className="py-2.5 font-medium">
                                                {order.bot?.symbol?.replace("USDT", "/USDT") || "-"}
                                            </td>
                                            <td className="py-2.5">
                                                <Badge
                                                    variant={order.side === "buy" ? "default" : "destructive"}
                                                    className="text-[10px]"
                                                >
                                                    {order.side === "buy" ? "Compra" : "Venta"}
                                                </Badge>
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {formatCurrency(order.price)}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums">
                                                {order.quantity}
                                            </td>
                                            <td className="py-2.5 text-right tabular-nums text-muted-foreground">
                                                #{order.grid_level}
                                            </td>
                                            <td className="py-2.5 text-right text-muted-foreground">
                                                {formatDate(order.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </OrdersLayout>
        </AuthenticatedLayout>
    );
}
