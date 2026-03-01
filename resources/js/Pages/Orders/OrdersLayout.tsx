import { cn } from "@/lib/utils";
import { ORDER_NAV_ITEMS } from "@/utils/constants";
import { Link } from "@inertiajs/react";
import { PropsWithChildren } from "react";

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
            {/* Sidebar */}
            <aside className="hidden w-56 shrink-0 border-r bg-card/40 lg:block">
                <nav className="p-4 space-y-5">
                    {ORDER_NAV_ITEMS.map((group) => (
                        <div key={group.group}>
                            <p className="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                {group.group}
                            </p>
                            <div className="space-y-0.5">
                                {group.items.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={cn(
                                            "flex items-center rounded-md px-2 py-1.5 text-sm transition-colors",
                                            isActive(item.routeName)
                                                ? "bg-primary/10 text-primary font-medium"
                                                : "text-muted-foreground hover:bg-accent hover:text-foreground",
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>
            </aside>

            {/* Main content */}
            <div className="flex-1 overflow-auto">{children}</div>
        </div>
    );
}
