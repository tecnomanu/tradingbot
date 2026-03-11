import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from "@/components/ui/resizable";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { BinanceAccount, Bot, GridConfig } from "@/types/bot";
import { Head, useForm } from "@inertiajs/react";
import { Link } from "@inertiajs/react";
import axios from "axios";
import { X } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import AccountPanel from "./components/AccountPanel";
import BotFormAdvanced from "./components/BotFormAdvanced";
import BotTable from "./components/BotTable";
import ConfirmParamsModal from "./components/ConfirmParamsModal";
import OrderBook from "./components/OrderBook";
import TickerBar from "./components/TickerBar";
import TradingViewChart, { ChartOrder } from "./components/TradingViewChart";

interface EditBot {
    id: number;
    binance_account_id: string;
    name: string;
    symbol: string;
    side: string;
    price_lower: string;
    price_upper: string;
    grid_count: string;
    investment: string;
    leverage: string;
    slippage: string;
    stop_loss_price: string;
    take_profit_price: string;
    status: string;
}

interface ActiveBotConfig {
    price_lower: number;
    price_upper: number;
    grid_count: number;
    side: string;
    symbol: string;
}

interface BotsIndexProps {
    bots: Bot[];
    binanceAccounts: BinanceAccount[];
    activeBotOrders?: ChartOrder[];
    activeBotConfig?: ActiveBotConfig | null;
    editBot?: EditBot | null;
}

export default function Index({
    bots,
    binanceAccounts,
    activeBotOrders = [],
    activeBotConfig,
    editBot,
}: BotsIndexProps) {
    const isEditing = !!editBot;
    const [showConfirm, setShowConfirm] = useState(false);
    const [calculating, setCalculating] = useState(false);
    const [currentPrice, setCurrentPrice] = useState<number | null>(null);
    const [gridConfig, setGridConfig] = useState<GridConfig | null>(null);
    const [balance, setBalance] = useState<number | null>(null);
    const [fetchingBalance, setFetchingBalance] = useState(false);
    const [showGridLines, setShowGridLines] = useState(true);

    const { data, setData, post, put, processing, errors } = useForm({
        binance_account_id: editBot?.binance_account_id ??
            (binanceAccounts?.length > 0
                ? binanceAccounts[0].id.toString()
                : ""),
        name: editBot?.name ?? "Bot Grid Futuros",
        symbol: editBot?.symbol ?? "BTCUSDT",
        side: editBot?.side ?? "long",
        price_lower: editBot?.price_lower ?? "",
        price_upper: editBot?.price_upper ?? "",
        grid_count: editBot?.grid_count ?? "15",
        investment: editBot?.investment ?? "3000",
        leverage: editBot?.leverage ?? "3",
        slippage: editBot?.slippage ?? "0.1",
        stop_loss_price: editBot?.stop_loss_price ?? "",
        take_profit_price: editBot?.take_profit_price ?? "",
    });

    // Chart always uses form data so the user sees grid changes in real-time.
    // Orders come from the active bot regardless.
    const chartSymbol = data.symbol;
    const chartLower = data.price_lower ? parseFloat(data.price_lower) : undefined;
    const chartUpper = data.price_upper ? parseFloat(data.price_upper) : undefined;
    const chartGridCount = showGridLines && data.grid_count ? parseInt(data.grid_count) : undefined;
    const chartSide = data.side;

    useEffect(() => {
        if (!data.binance_account_id) {
            setBalance(null);
            return;
        }
        const fetchBalance = async () => {
            setFetchingBalance(true);
            try {
                const res = await axios.get(
                    `/binance-accounts/${data.binance_account_id}/balance`,
                );
                setBalance(
                    res.data.data?.total_usdt ?? res.data?.total_usdt ?? 0,
                );
            } catch {
                setBalance(0);
            } finally {
                setFetchingBalance(false);
            }
        };
        fetchBalance();
    }, [data.binance_account_id]);

    const calculateParams = async () => {
        setCalculating(true);
        try {
            const res = await axios.post("/bots/calculate-grid", data);
            setGridConfig(res.data.data);
            setShowConfirm(true);
        } catch (err: any) {
            alert(
                err.response?.data?.message || "Error al calcular parámetros",
            );
        } finally {
            setCalculating(false);
        }
    };

    const handleConfirm = () => {
        if (isEditing) {
            put(`/bots/${editBot!.id}`);
        } else {
            post("/bots");
        }
    };

    return (
        <AuthenticatedLayout fullWidth>
            <Head title={isEditing ? `Editar Bot #${editBot!.id}` : "Trading Terminal"} />

            <div className="w-full h-[calc(100vh-56px)] overflow-hidden bg-background text-foreground text-sm">
                <ResizablePanelGroup direction="vertical">
                    <ResizablePanel defaultSize={72} minSize={45}>
                        <div className="h-full flex flex-col">
                            {isEditing && (
                                <div className="bg-yellow-500/10 border-b border-yellow-500/30 px-4 py-1.5 text-xs flex items-center gap-2">
                                    <span className="inline-block w-2 h-2 rounded-full bg-yellow-500" />
                                    <span className="font-medium text-yellow-600 dark:text-yellow-400">
                                        Modo edición — {editBot!.name} ({editBot!.symbol.replace("USDT", "/USDT")})
                                    </span>
                                    {editBot!.status === "active" && (
                                        <span className="text-yellow-500/70 ml-2">
                                            Bot activo: al guardar se detendrá, aplicarán los cambios y reiniciará automáticamente.
                                        </span>
                                    )}
                                    <div className="ml-auto flex items-center gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 text-[10px] px-2 text-yellow-600 dark:text-yellow-400 hover:text-foreground"
                                            asChild
                                        >
                                            <Link href="/bots">
                                                <X className="mr-1 h-3 w-3" /> Cancelar edición
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            )}

                            <TickerBar
                                symbol={chartSymbol}
                                onPriceUpdate={setCurrentPrice}
                            />

                            <div className="flex-1 min-h-0">
                                <ResizablePanelGroup direction="horizontal">
                                    <ResizablePanel
                                        defaultSize={78}
                                        minSize={50}
                                    >
                                        <ResizablePanelGroup direction="horizontal">
                                            <ResizablePanel
                                                defaultSize={80}
                                                minSize={50}
                                            >
                                                <TradingViewChart
                                                    symbol={chartSymbol}
                                                    lowerPrice={chartLower}
                                                    upperPrice={chartUpper}
                                                    gridCount={chartGridCount}
                                                    side={chartSide}
                                                    orders={activeBotOrders}
                                                />
                                            </ResizablePanel>

                                            <ResizableHandle className="hidden lg:flex" />

                                            <ResizablePanel
                                                defaultSize={20}
                                                minSize={12}
                                                maxSize={30}
                                                className="hidden lg:block"
                                            >
                                                <OrderBook
                                                    symbol={chartSymbol}
                                                    currentPrice={currentPrice}
                                                />
                                            </ResizablePanel>
                                        </ResizablePanelGroup>
                                    </ResizablePanel>

                                    <ResizableHandle />

                                    <ResizablePanel
                                        defaultSize={22}
                                        minSize={18}
                                        maxSize={35}
                                    >
                                        <div className="h-full flex flex-col bg-card/30 border-l">
                                            <div className="flex border-b text-xs font-medium">
                                                <div className={`px-4 py-2.5 flex-1 text-center cursor-pointer ${
                                                    isEditing
                                                        ? "border-b-2 border-yellow-500 text-yellow-600 dark:text-yellow-400"
                                                        : "border-b-2 border-primary"
                                                }`}>
                                                    {isEditing
                                                        ? "Editar Bot"
                                                        : "FuturosBot"}
                                                </div>
                                                {!isEditing && (
                                                    <div className="px-4 py-2.5 text-muted-foreground flex-1 text-center cursor-pointer hover:bg-muted/30 transition-colors">
                                                        Comercio de futuros
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 overflow-hidden">
                                                <BotFormAdvanced
                                                    data={data}
                                                    setData={setData}
                                                    errors={errors}
                                                    processing={calculating}
                                                    accounts={binanceAccounts}
                                                    currentPrice={currentPrice}
                                                    onCalculate={calculateParams}
                                                    balance={balance}
                                                    fetchingBalance={fetchingBalance}
                                                    isEditing={isEditing}
                                                    editBotId={editBot?.id}
                                                    showGridLines={showGridLines}
                                                    onShowGridLinesChange={setShowGridLines}
                                                />
                                            </div>
                                        </div>
                                    </ResizablePanel>
                                </ResizablePanelGroup>
                            </div>
                        </div>
                    </ResizablePanel>

                    <ResizableHandle withHandle />

                    <ResizablePanel defaultSize={28} minSize={15}>
                        <ResizablePanelGroup direction="horizontal">
                            <ResizablePanel defaultSize={75} minSize={50}>
                                <BotTable bots={bots} />
                            </ResizablePanel>

                            <ResizableHandle />

                            <ResizablePanel
                                defaultSize={25}
                                minSize={18}
                                maxSize={35}
                            >
                                <AccountPanel
                                    balance={balance}
                                    fetchingBalance={fetchingBalance}
                                />
                            </ResizablePanel>
                        </ResizablePanelGroup>
                    </ResizablePanel>
                </ResizablePanelGroup>
            </div>

            {gridConfig && (
                <ConfirmParamsModal
                    open={showConfirm}
                    onClose={() => setShowConfirm(false)}
                    onConfirm={handleConfirm}
                    processing={processing}
                    config={gridConfig}
                    formData={data}
                    isEditing={isEditing}
                    editBotStatus={editBot?.status}
                />
            )}
        </AuthenticatedLayout>
    );
}
