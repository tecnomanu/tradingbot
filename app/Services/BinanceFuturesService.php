<?php

namespace App\Services;

use App\Constants\BinanceConstants;
use App\Models\BinanceAccount;
use Binance\Client\DerivativesTradingUsdsFutures\Api\DerivativesTradingUsdsFuturesRestApi;
use Binance\Client\DerivativesTradingUsdsFutures\DerivativesTradingUsdsFuturesRestApiUtil;
use Binance\Client\DerivativesTradingUsdsFutures\Model\ChangeInitialLeverageRequest;
use Binance\Client\DerivativesTradingUsdsFutures\Model\ChangeMarginTypeRequest;
use Binance\Client\DerivativesTradingUsdsFutures\Model\NewOrderRequest;
use Binance\Client\DerivativesTradingUsdsFutures\Model\Side;
use Binance\Common\ApiException;
use Exception;
use App\Support\BotLog as Log;

class BinanceFuturesService
{
    private array $clients = [];

    public function createClient(BinanceAccount $account): DerivativesTradingUsdsFuturesRestApi
    {
        $cacheKey = $account->id;

        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }

        $baseUrl = $account->is_testnet
            ? BinanceConstants::TESTNET_FUTURES_URL
            : BinanceConstants::FUTURES_BASE_URL;

        $config = DerivativesTradingUsdsFuturesRestApiUtil::getConfigurationBuilder()
            ->apiKey($account->api_key)
            ->secretKey($account->api_secret)
            ->url($baseUrl)
            ->build();

        $this->clients[$cacheKey] = new DerivativesTradingUsdsFuturesRestApi($config);

        return $this->clients[$cacheKey];
    }

    /**
     * @throws Exception
     */
    public function testConnection(BinanceAccount $account): array
    {
        $client = $this->createClient($account);
        $response = $client->accountInformationV3();

        $data = $response->getData();

        return [
            'can_trade' => true,
            'total_wallet_balance' => (float) ($data->getTotalWalletBalance() ?? 0),
            'available_balance' => (float) ($data->getAvailableBalance() ?? 0),
        ];
    }

    public function getAccountBalance(BinanceAccount $account): array
    {
        $client = $this->createClient($account);
        $response = $client->futuresAccountBalanceV3();

        $data = $response->getData();
        $items = method_exists($data, 'getItems') ? $data->getItems() : (is_array($data) ? $data : []);

        $balances = [];
        $availableUsdt = 0.0;
        $totalUsdt = 0.0;

        foreach ($items as $item) {
            $balance = (float) $item->getBalance();
            $available = (float) $item->getAvailableBalance();

            if ($balance > 0 || $available > 0) {
                $asset = $item->getAsset();
                $balances[] = [
                    'asset' => $asset,
                    'balance' => $balance,
                    'available' => $available,
                ];

                if ($asset === 'USDT') {
                    $availableUsdt = $available;
                    $totalUsdt = $balance;
                }
            }
        }

        return [
            'balances' => $balances,
            'available_usdt' => $availableUsdt,
            'total_usdt' => $totalUsdt,
        ];
    }

    public function getCurrentPrice(BinanceAccount $account, string $symbol): ?float
    {
        try {
            $client = $this->createClient($account);
            $response = $client->symbolPriceTickerV2($symbol);
            $data = $response->getData();

            if (is_array($data) && !empty($data)) {
                $price = (float) $data[0]->getPrice();
                if ($price > 0) {
                    return $price;
                }
            } elseif ($data) {
                $price = (float) $data->getPrice();
                if ($price > 0) {
                    return $price;
                }
            }

            // SDK may return empty on testnet; fall back to direct HTTP
            return $this->getCurrentPriceHttp($account, $symbol);
        } catch (Exception $e) {
            Log::warning('BinanceFutures: SDK price failed, trying HTTP fallback', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return $this->getCurrentPriceHttp($account, $symbol);
        }
    }

    private function getCurrentPriceHttp(BinanceAccount $account, string $symbol): ?float
    {
        try {
            $baseUrl = $account->is_testnet
                ? BinanceConstants::TESTNET_FUTURES_URL
                : BinanceConstants::FUTURES_BASE_URL;

            $url = $baseUrl . '/fapi/v1/ticker/price?symbol=' . $symbol;
            $json = file_get_contents($url);

            if ($json === false) {
                return null;
            }

            $data = json_decode($json, true);
            return (float) ($data['price'] ?? 0) ?: null;
        } catch (Exception $e) {
            Log::error('BinanceFutures: HTTP price fallback failed', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function setLeverage(BinanceAccount $account, string $symbol, int $leverage): bool
    {
        try {
            $client = $this->createClient($account);
            $request = new ChangeInitialLeverageRequest([
                'symbol' => $symbol,
                'leverage' => $leverage,
            ]);
            $client->changeInitialLeverage($request);
            return true;
        } catch (ApiException $e) {
            Log::warning('BinanceFutures: failed to set leverage', [
                'symbol' => $symbol,
                'leverage' => $leverage,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function setMarginType(BinanceAccount $account, string $symbol, string $marginType = 'CROSSED'): bool
    {
        try {
            $client = $this->createClient($account);
            $request = new ChangeMarginTypeRequest([
                'symbol' => $symbol,
                'marginType' => $marginType,
            ]);
            $client->changeMarginType($request);
            return true;
        } catch (ApiException $e) {
            // -4046 = "No need to change margin type" (already set)
            if (str_contains($e->getMessage(), '-4046')) {
                return true;
            }
            Log::warning('BinanceFutures: failed to set margin type', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Place a LIMIT order on Binance Futures.
     *
     * @return array{orderId: int, clientOrderId: string, status: string}
     * @throws Exception
     */
    public function placeLimitOrder(
        BinanceAccount $account,
        string $symbol,
        string $side,
        float $quantity,
        float $price,
        ?string $clientOrderId = null,
    ): array {
        $client = $this->createClient($account);

        $params = [
            'symbol' => $symbol,
            'side' => $side === BinanceConstants::SIDE_BUY ? Side::BUY : Side::SELL,
            'type' => 'LIMIT',
            'timeInForce' => 'GTC',
            'quantity' => $quantity,
            'price' => $price,
        ];

        if ($clientOrderId) {
            $params['newClientOrderId'] = $clientOrderId;
        }

        $request = new NewOrderRequest($params);
        $response = $client->newOrder($request);
        $data = $response->getData();

        return [
            'orderId' => $data->getOrderId(),
            'clientOrderId' => $data->getClientOrderId(),
            'status' => $data->getStatus(),
        ];
    }

    public function placeMarketOrder(
        BinanceAccount $account,
        string $symbol,
        string $side,
        float $quantity,
    ): array {
        $client = $this->createClient($account);

        $request = new NewOrderRequest([
            'symbol' => $symbol,
            'side' => $side === BinanceConstants::SIDE_BUY ? Side::BUY : Side::SELL,
            'type' => 'MARKET',
            'quantity' => $quantity,
        ]);

        $response = $client->newOrder($request);
        $data = $response->getData();

        return [
            'orderId' => $data->getOrderId(),
            'clientOrderId' => $data->getClientOrderId(),
            'status' => $data->getStatus(),
        ];
    }

    /**
     * Cancel a single order by Binance order ID.
     */
    public function cancelOrder(BinanceAccount $account, string $symbol, int $orderId): bool
    {
        try {
            $client = $this->createClient($account);
            $client->cancelOrder($symbol, $orderId);
            return true;
        } catch (ApiException $e) {
            // -2011 = "Unknown order" (already cancelled/filled)
            if (str_contains($e->getMessage(), '-2011')) {
                return true;
            }
            Log::error('BinanceFutures: failed to cancel order', [
                'symbol' => $symbol,
                'orderId' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cancel all open orders for a symbol.
     */
    public function cancelAllOrders(BinanceAccount $account, string $symbol): bool
    {
        try {
            $client = $this->createClient($account);
            $client->cancelAllOpenOrders($symbol);
            return true;
        } catch (ApiException $e) {
            Log::error('BinanceFutures: failed to cancel all orders', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Query a single order status by Binance order ID.
     *
     * @return array{orderId: int, status: string, executedQty: float, avgPrice: float}|null
     */
    public function queryOrder(BinanceAccount $account, string $symbol, int $orderId): ?array
    {
        try {
            $client = $this->createClient($account);
            $response = $client->queryOrder($symbol, $orderId);
            $data = $response->getData();

            return [
                'orderId' => $data->getOrderId(),
                'status' => $data->getStatus(),
                'executedQty' => (float) $data->getExecutedQty(),
                'avgPrice' => (float) $data->getAvgPrice(),
                'origQty' => (float) $data->getOrigQty(),
                'price' => (float) $data->getPrice(),
                'side' => $data->getSide(),
            ];
        } catch (Exception $e) {
            Log::error('BinanceFutures: failed to query order', [
                'symbol' => $symbol,
                'orderId' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all current open orders for a symbol.
     *
     * @return array<array{orderId: int, price: float, origQty: float, side: string, status: string}>
     */
    public function getOpenOrders(BinanceAccount $account, string $symbol): array
    {
        try {
            $client = $this->createClient($account);
            $response = $client->currentAllOpenOrders($symbol);

            $data = $response->getData();
            $items = method_exists($data, 'getItems') ? $data->getItems() : (is_array($data) ? $data : []);

            $orders = [];

            foreach ($items as $order) {
                $orders[] = [
                    'orderId' => $order->getOrderId(),
                    'price' => (float) $order->getPrice(),
                    'origQty' => (float) $order->getOrigQty(),
                    'executedQty' => (float) $order->getExecutedQty(),
                    'side' => $order->getSide(),
                    'status' => $order->getStatus(),
                    'clientOrderId' => $order->getClientOrderId(),
                ];
            }

            return $orders;
        } catch (Exception $e) {
            Log::error('BinanceFutures: failed to get open orders', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get current positions for a symbol.
     */
    public function getPositions(BinanceAccount $account, string $symbol): array
    {
        try {
            $client = $this->createClient($account);
            $response = $client->positionInformationV3($symbol);

            $data = $response->getData();
            $items = method_exists($data, 'getItems') ? $data->getItems() : (is_array($data) ? $data : []);

            $positions = [];

            foreach ($items as $pos) {
                $positions[] = [
                    'symbol' => $pos->getSymbol(),
                    'positionAmt' => (float) $pos->getPositionAmt(),
                    'entryPrice' => (float) $pos->getEntryPrice(),
                    'unrealizedProfit' => (float) $pos->getUnRealizedProfit(),
                    'liquidationPrice' => (float) $pos->getLiquidationPrice(),
                    'positionSide' => $pos->getPositionSide(),
                ];
            }

            return $positions;
        } catch (Exception $e) {
            Log::error('BinanceFutures: failed to get positions', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Format quantity to Binance precision rules.
     * Futures pairs have specific step sizes; this is a reasonable default.
     */
    public function formatQuantity(string $symbol, float $quantity): float
    {
        $decimals = BinanceConstants::QUANTITY_PRECISION[$symbol] ?? 3;
        return round($quantity, $decimals);
    }

    public function formatPrice(string $symbol, float $price): float
    {
        $decimals = BinanceConstants::PRICE_PRECISION[$symbol] ?? 2;
        return round($price, $decimals);
    }
}
