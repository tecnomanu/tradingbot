import { Button } from "@/components/ui/button";
import { Loader2 } from "lucide-react";

interface AccountPanelProps {
    balance: number | null;
    fetchingBalance: boolean;
}

export default function AccountPanel({
    balance,
    fetchingBalance,
}: AccountPanelProps) {
    return (
        <div className="h-full flex flex-col bg-card/30 border-l text-foreground">
            <div className="px-4 py-3 border-b">
                <h3 className="text-sm font-semibold">Cuenta principal</h3>
            </div>

            <div className="flex-1 p-4 space-y-4">
                <p className="text-[10px] text-muted-foreground uppercase tracking-wider">
                    Activos(USDT)
                </p>

                <div className="space-y-3">
                    <div className="flex justify-between items-center text-xs">
                        <span className="text-muted-foreground">
                            Disponible
                        </span>
                        <span className="tabular-nums">
                            {fetchingBalance ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                            ) : balance !== null ? (
                                `${balance.toLocaleString("en-US", { minimumFractionDigits: 2 })} USDT`
                            ) : (
                                "--"
                            )}
                        </span>
                    </div>

                    <div className="flex justify-between items-center text-xs">
                        <span className="text-muted-foreground">
                            Cantidad total
                        </span>
                        <span className="tabular-nums">
                            {fetchingBalance ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                            ) : balance !== null ? (
                                `${balance.toLocaleString("en-US", { minimumFractionDigits: 2 })} USDT`
                            ) : (
                                "--"
                            )}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-2 pt-4">
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs"
                    >
                        Depositar
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs"
                    >
                        Transfer
                    </Button>
                </div>
            </div>
        </div>
    );
}
