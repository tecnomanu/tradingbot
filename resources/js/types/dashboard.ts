export interface ExtendedStats {
    total_orders: number;
    open_orders: number;
    filled_orders: number;
    filled_24h: number;
    rounds_24h: number;
    accounts_total: number;
    accounts_active: number;
    accounts_testnet: number;
    ai_conversations: number;
    ai_actions: number;
    last_ai_consult: string | null;
    total_bots_stopped: number;
    total_bots_error: number;
    trend_pnl: number;
}

export interface RecentOrder {
    id: number;
    symbol: string;
    side: string;
    price: number;
    quantity: number;
    pnl: number;
    filled_at: string;
}

export interface RecentAction {
    id: number;
    symbol: string;
    action: string;
    source: string;
    actor_label: string;
    created_at: string;
}
