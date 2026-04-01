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
    { label: "Inicio", href: "/dashboard", routeName: "dashboard" },
    { label: "Operaciones", href: "/bots", routeName: "bots.*" },
    { label: "Actividad", href: "/orders/positions", routeName: "orders.*" },
    { label: "Agente IA", href: "/ai-agent", routeName: "ai-agent.*" },
    {
        label: "Cuentas",
        href: "/binance-accounts",
        routeName: "binance-accounts.*",
    },
] as const;

export const REFRESH_INTERVAL_MS = 30_000;
export const RECENT_ACTIVITY_THRESHOLD_MS = 300_000;
export const MAINTENANCE_MARGIN_RATE = 0.004;
export const BINANCE_WS_URL = "wss://stream.binance.com:9443";
export const ORDER_BOOK_DEPTH = 14;

export const ORDER_NAV_ITEMS = [
    {
        label: "Posiciones",
        href: "/orders/positions",
        routeName: "orders.positions",
    },
    {
        label: "Órdenes",
        href: "/orders/history",
        routeName: "orders.history",
    },
    {
        label: "Grid Bots",
        href: "/orders/bots",
        routeName: "orders.bots",
    },
] as const;
