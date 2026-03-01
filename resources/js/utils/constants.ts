export const SUPPORTED_PAIRS = [
    "BTCUSDT",
    "ETHUSDT",
    "BNBUSDT",
    "SOLUSDT",
    "DOGEUSDT",
    "XRPUSDT",
    "ADAUSDT",
    "AVAXUSDT",
    "DOTUSDT",
    "MATICUSDT",
    "LINKUSDT",
    "UNIUSDT",
    "LTCUSDT",
    "ATOMUSDT",
    "NEARUSDT",
] as const;

export const LEVERAGE_OPTIONS = [
    1, 2, 3, 5, 10, 20, 25, 50, 75, 100, 125,
] as const;

export const NAV_ITEMS = [
    { label: "Dashboard", href: "/dashboard", routeName: "dashboard" },
    { label: "Trading", href: "/bots", routeName: "bots.*" },
    { label: "Ordenes", href: "/orders/active-bots", routeName: "orders.*" },
    { label: "AI Agent", href: "/ai-agent", routeName: "ai-agent.*" },
    {
        label: "Cuentas",
        href: "/binance-accounts",
        routeName: "binance-accounts.*",
    },
] as const;

export const ORDER_NAV_ITEMS = [
    {
        group: "Grid Bot",
        items: [
            {
                label: "Ordenes activas",
                href: "/orders/active-bots",
                routeName: "orders.active-bots",
            },
            {
                label: "Historial de bots",
                href: "/orders/bot-history",
                routeName: "orders.bot-history",
            },
        ],
    },
    {
        group: "Ordenes de Futuros",
        items: [
            {
                label: "Ordenes abiertas",
                href: "/orders/open",
                routeName: "orders.open",
            },
            {
                label: "Historial",
                href: "/orders/history",
                routeName: "orders.history",
            },
        ],
    },
    {
        group: "Posiciones",
        items: [
            {
                label: "Posiciones activas",
                href: "/orders/positions",
                routeName: "orders.positions",
            },
        ],
    },
] as const;
