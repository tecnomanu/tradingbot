import { cn } from "@/lib/utils";
import { ORDER_NAV_ITEMS } from "@/utils/constants";
import { Link } from "@inertiajs/react";
import { Bot, History, BarChart3 } from "lucide-react";
import { PropsWithChildren } from "react";

const ICONS: Record<string, React.ElementType> = {
    "Grid Bots": Bot,
    "Órdenes": History,
    Posiciones: BarChart3,
};

export function OrdersLayout({ children }: PropsWithChildren) {
    const isActive = (pattern: string) => {
        try {
            return route().current(pattern);
        } catch {
            return false;
        }
    };

    return (
        <div className="flex min-h-[calc(100vh-3.5rem)] text-foreground">
            <aside className="hidden w-48 shrink-0 border-r bg-card/40 lg:block">
                <nav className="p-3 space-y-0.5">
                    {ORDER_NAV_ITEMS.map((item) => {
                        const Icon = ICONS[item.label];
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    "flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                                    isActive(item.routeName)
                                        ? "bg-primary/10 text-primary font-medium"
                                        : "text-muted-foreground hover:bg-accent hover:text-foreground",
                                )}
                            >
                                {Icon && <Icon className="h-4 w-4" />}
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>
            </aside>
            <div className="flex-1 overflow-auto">{children}</div>
        </div>
    );
}
