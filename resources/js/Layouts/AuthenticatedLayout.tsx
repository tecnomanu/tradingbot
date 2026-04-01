import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { useDarkMode } from "@/hooks/useDarkMode";
import { cn } from "@/lib/utils";
import { NAV_ITEMS, ORDER_NAV_ITEMS } from "@/utils/constants";
import { Link, router, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { Toaster, toast } from "sonner";
import {
    LogOut,
    Menu,
    Moon,
    Settings,
    Sun,
    TrendingUp,
    User,
} from "lucide-react";
import { PropsWithChildren, ReactNode, useState } from "react";

export default function AuthenticatedLayout({
    header,
    children,
    fullWidth = false,
}: PropsWithChildren<{ header?: ReactNode; fullWidth?: boolean }>) {
    const { auth, flash } = usePage().props as { auth: { user: { name: string; email: string } }; flash?: { success?: string; error?: string } };
    const user = auth.user;
    const { isDark, toggle } = useDarkMode();
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const isActive = (pattern: string) => {
        try {
            return route().current(pattern);
        } catch {
            return false;
        }
    };

    const NavLinks = ({ mobile = false }: { mobile?: boolean }) => (
        <>
            {NAV_ITEMS.map((item) => {
                const isActivityItem = item.routeName === "orders.*";
                return (
                    <div key={item.routeName}>
                        <Link
                            href={item.href}
                            onClick={() => mobile && setMobileOpen(false)}
                            className={cn(
                                "inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                                isActive(item.routeName)
                                    ? "bg-primary/10 text-primary"
                                    : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                            )}
                        >
                            {item.label}
                        </Link>
                        {/* Sub-links of Actividad visible only in mobile nav */}
                        {mobile && isActivityItem && (
                            <div className="ml-4 flex flex-col gap-0.5 mt-0.5">
                                {ORDER_NAV_ITEMS.map((sub) => (
                                    <Link
                                        key={sub.routeName}
                                        href={sub.href}
                                        onClick={() => setMobileOpen(false)}
                                        className={cn(
                                            "block rounded-md px-3 py-1.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                                            isActive(sub.routeName)
                                                ? "bg-primary/10 text-primary"
                                                : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
                                        )}
                                    >
                                        {sub.label}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );

    return (
        <div className="min-h-screen bg-background">
            <Toaster theme="system" richColors position="top-right" />
            {/* Skip link for keyboard users */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-primary-foreground"
            >
                Saltar al contenido
            </a>
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="flex h-14 items-center gap-4 px-4 sm:px-6">
                    {/* Mobile menu */}
                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="md:hidden"
                            >
                                <Menu className="h-5 w-5" />
                                <span className="sr-only">Menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="w-64">
                            <div className="flex flex-col gap-4 py-4">
                                <Link
                                    href="/dashboard"
                                    className="flex items-center gap-2 px-2 font-semibold"
                                >
                                    <div className="flex h-6 w-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                        <TrendingUp className="h-4 w-4" />
                                    </div>
                                    GridBot
                                </Link>
                                <Separator />
                                <nav className="flex flex-col gap-1">
                                    <NavLinks mobile />
                                </nav>
                            </div>
                        </SheetContent>
                    </Sheet>

                    {/* Logo */}
                    <Link
                        href="/dashboard"
                        className="flex items-center gap-2 font-semibold text-foreground"
                    >
                        <div className="flex h-7 w-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <TrendingUp className="h-4 w-4" />
                        </div>
                        <span className="hidden sm:inline-block">GridBot</span>
                    </Link>

                    {/* Desktop Nav */}
                    <nav className="hidden items-center gap-1 md:flex">
                        <NavLinks />
                    </nav>

                    {/* Right side */}
                    <div className="ml-auto flex items-center gap-2">
                        {/* Theme toggle */}
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={toggle}
                            className="h-8 w-8"
                        >
                            {isDark ? (
                                <Sun className="h-4 w-4" />
                            ) : (
                                <Moon className="h-4 w-4" />
                            )}
                            <span className="sr-only">Cambiar tema</span>
                        </Button>

                        {/* User dropdown */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="relative h-8 gap-2 rounded-full pl-2 pr-3 text-foreground"
                                >
                                    <Avatar className="h-6 w-6">
                                        <AvatarFallback className="bg-primary text-[10px] text-primary-foreground">
                                            {user.name.charAt(0).toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden text-sm font-medium sm:inline-block">
                                        {user.name}
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-56 bg-card"
                                align="end"
                                forceMount
                            >
                                <DropdownMenuLabel className="font-normal">
                                    <div className="flex flex-col space-y-1">
                                        <p className="text-sm font-medium leading-none">
                                            {user.name}
                                        </p>
                                        <p className="text-xs leading-none text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route("profile.edit")}
                                        className="cursor-pointer"
                                    >
                                        <User className="mr-2 h-4 w-4" />
                                        Perfil
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href="/binance-accounts"
                                        className="cursor-pointer"
                                    >
                                        <Settings className="mr-2 h-4 w-4" />
                                        Cuentas Binance
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="cursor-pointer text-destructive focus:text-destructive"
                                    onClick={() => router.post(route("logout"))}
                                >
                                    <LogOut className="mr-2 h-4 w-4" />
                                    Cerrar sesión
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>

            {/* Page Header */}
            {header && !fullWidth && (
                <div className="border-b">
                    <div className="px-4 py-4 sm:px-6">{header}</div>
                </div>
            )}

            {/* Main Content */}
            <main
                id="main-content"
                className={cn(
                    "flex-1",
                    fullWidth ? "p-0" : "px-4 py-6 sm:px-6",
                )}
            >
                {children}
            </main>
        </div>
    );
}
