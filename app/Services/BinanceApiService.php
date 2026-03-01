<?php

namespace App\Services;

use App\Constants\BinanceConstants;
use App\Models\BinanceAccount;
use Exception;
use App\Support\BotLog as Log;

class BinanceApiService
{
    public function __construct(
        private BinanceFuturesService $futuresService,
    ) {}

    /**
     * Test connection to Binance API.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(BinanceAccount $account): array
    {
        try {
            $data = $this->futuresService->testConnection($account);

            $account->update(['last_connected_at' => now()]);

            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::warning('Binance connection test failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get account balance.
     *
     * @return array{balances: array, total_usdt: float}
     */
    public function getAccountBalance(BinanceAccount $account): array
    {
        try {
            return $this->futuresService->getAccountBalance($account);
        } catch (Exception $e) {
            Log::warning('Failed to get Binance balance', ['error' => $e->getMessage()]);
            return ['balances' => [], 'total_usdt' => 0];
        }
    }

    /**
     * Get current price for a symbol (public endpoint, uses first active account or default).
     */
    public function getCurrentPrice(string $symbol): ?float
    {
        try {
            $account = BinanceAccount::where('is_active', true)->first();

            if ($account) {
                return $this->futuresService->getCurrentPrice($account, $symbol);
            }

            // Fallback: use Binance public REST API
            $url = BinanceConstants::FUTURES_BASE_URL . '/fapi/v2/ticker/price?symbol=' . $symbol;
            $response = file_get_contents($url);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            return (float) ($data['price'] ?? 0);
        } catch (Exception $e) {
            Log::error('Failed to get price', ['symbol' => $symbol, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get positions for a symbol from Binance.
     */
    public function getPositions(BinanceAccount $account, string $symbol): array
    {
        try {
            return $this->futuresService->getPositions($account, $symbol);
        } catch (Exception $e) {
            Log::warning('Failed to get positions', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get supported trading pairs.
     */
    public function getSupportedPairs(): array
    {
        return BinanceConstants::SUPPORTED_PAIRS;
    }
}
