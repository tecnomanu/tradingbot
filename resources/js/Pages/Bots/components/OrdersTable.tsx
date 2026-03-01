import { Badge } from "@/components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Order } from "@/types/bot";
import { formatCurrency, formatDate } from "@/utils/formatters";

interface OrdersTableProps {
    orders: { data: Order[]; current_page: number; last_page: number };
}

export default function OrdersTable({ orders }: OrdersTableProps) {
    if (!orders.data.length) {
        return (
            <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
                Sin órdenes registradas
            </div>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Lado</TableHead>
                    <TableHead>Precio</TableHead>
                    <TableHead>Cantidad</TableHead>
                    <TableHead>PNL</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Fecha</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {orders.data.map((order) => {
                    const pnl = order.pnl ?? 0;
                    return (
                        <TableRow key={order.id}>
                            <TableCell>
                                <Badge
                                    variant={
                                        order.side === "buy"
                                            ? "default"
                                            : "destructive"
                                    }
                                    className="text-xs"
                                >
                                    {order.side === "buy" ? "Compra" : "Venta"}
                                </Badge>
                            </TableCell>
                            <TableCell className="font-mono text-sm">
                                {formatCurrency(order.price)}
                            </TableCell>
                            <TableCell className="text-sm">
                                {order.quantity}
                            </TableCell>
                            <TableCell>
                                <span
                                    className={
                                        pnl > 0
                                            ? "text-green-500 font-medium"
                                            : pnl < 0
                                              ? "text-destructive font-medium"
                                              : "text-muted-foreground"
                                    }
                                >
                                    {pnl > 0 ? "+" : ""}
                                    {formatCurrency(pnl)}
                                </span>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant={
                                        order.status === "filled"
                                            ? "default"
                                            : order.status === "cancelled"
                                              ? "secondary"
                                              : "outline"
                                    }
                                    className="text-xs"
                                >
                                    {order.status}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-right text-xs text-muted-foreground">
                                {formatDate(order.created_at)}
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </Table>
    );
}
