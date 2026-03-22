export interface BinanceAccount {
    id: number;
    label: string;
    masked_api_key: string;
    is_testnet: boolean;
    is_active: boolean;
    last_connected_at: string | null;
    bots_count: number;
    created_at: string;
}

export interface Bot {
    id: number;
    user_id: number;
    binance_account_id: number;
    name: string;
    symbol: string;
    side: BotSide;
    status: BotStatus;
    last_error_message?: string | null;
    price_lower: number;
    price_upper: number;
    grid_count: number;
    grid_mode?: string;
    investment: number;
    leverage: number;
    margin_type: string | null;
    slippage: number;
    real_investment: number;
    additional_margin: number;
    est_liquidation_price: number;
    profit_per_grid: number;
    commission_per_grid: number;
    total_pnl: number;
    grid_profit: number;
    total_fees: number;
    trend_pnl: number;
    total_rounds: number;
    rounds_24h: number;
    stop_loss_price: number | null;
    take_profit_price: number | null;
    ai_system_prompt: string | null;
    ai_user_prompt: string | null;
    ai_consultation_interval: number;
    ai_notify_telegram: boolean;
    ai_notify_events: string[] | null;
    risk_config: RiskConfig | null;
    risk_guard_reason: string | null;
    risk_guard_triggered_at: string | null;
    started_at: string | null;
    stopped_at: string | null;
    created_at: string;
    // Withcount
    open_orders_count?: number;
    filled_orders_count?: number;
    filled_24h?: number;
    // Computed
    pnl_percentage: number;
    price_range: string;
    // Relations
    binance_account?: { id: number; label: string };
    orders?: Order[];
}

export interface Order {
    id: number;
    bot_id: number;
    side: OrderSide;
    status: OrderStatus;
    price: number;
    quantity: number;
    grid_level: number;
    pnl: number | null;
    fee: number | null;
    binance_order_id: string | null;
    filled_at: string | null;
    created_at: string;
}

export interface BotPnlSnapshot {
    time: string;
    total_pnl: number;
    grid_profit: number;
    trend_pnl?: number;
}

export interface DrawdownMetrics {
    peak_pnl: number;
    current_pnl: number;
    max_drawdown: number;
    max_drawdown_pct: number;
    drawdown_duration_minutes: number | null;
    snapshots_used: number;
    data_since: string | null;
}

export interface RiskConfig {
    max_drawdown_pct?: number;
    min_liquidation_distance_pct?: number;
    max_price_out_of_range_pct?: number;
    max_consecutive_errors?: number;
    max_grid_rebuilds_per_hour?: number;
    emergency_stop?: boolean;
}

export interface RiskGuardStatus {
    effective_config: RiskConfig;
    is_triggered: boolean;
    reason: string | null;
    triggered_at: string | null;
}

export interface DashboardStats {
    total_bots: number;
    active_bots: number;
    total_investment: number;
    total_pnl: number;
    total_grid_profit: number;
}

export interface GridConfig {
    grid_levels: Record<number, number>;
    profit_per_grid: number;
    commission_per_grid: number;
    real_investment: number;
    additional_margin: number;
    est_liquidation_price: number;
    quantity_per_grid: number;
    grid_mode?: string;
    step_size?: number;
}

export interface RecentFill {
    id: number;
    side: string;
    price: number;
    quantity: number;
    pnl: number;
    fee: number | null;
    filled_at: string | null;
    filled_at_fmt: string;
}

export interface GridLimits {
    min: number;
    max: number;
    min_leverage: number;
    max_leverage: number;
    min_investment: number;
    recommended_slippage: number;
}

export interface SideOption {
    value: string;
    label: string;
    color: string;
}

export interface OrderStats {
    total_orders: number;
    open_orders: number;
    filled_orders: number;
    total_pnl: number;
}

export type BotStatus = "active" | "stopped" | "error" | "pending";
export type BotSide = "long" | "short" | "neutral";
export type OrderSide = "buy" | "sell";
export type OrderStatus = "open" | "filled" | "cancelled" | "partially_filled";

export interface AgentImpactBucket {
    pnl: number;
    hours: number;
    pnl_per_hour: number;
    intervals: number;
}

export interface AgentImpact {
    agent: AgentImpactBucket;
    system: AgentImpactBucket;
    agent_actions: { total: number; by_type: Record<string, number> };
    agent_actions_success: number;
    agent_actions_failed: number;
    runtime_hours: number;
    agent_coverage_pct: number;
    snapshots_used: number;
    data_since: string | null;
    influence_window_min: number;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    message: string;
    data: T;
}

// Minimal account shape for select dropdowns
export interface BinanceAccountOption {
    id: number;
    label: string;
    masked_key: string;
}
