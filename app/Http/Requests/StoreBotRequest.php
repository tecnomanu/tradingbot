<?php

namespace App\Http\Requests;

use App\Constants\BinanceConstants;
use App\Constants\GridConstants;
use App\Enums\BotSide;
use Illuminate\Foundation\Http\FormRequest;

class StoreBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'binance_account_id' => 'required|exists:binance_accounts,id',
            'name'               => 'required|string|max:255',
            'symbol'             => 'required|string|in:' . implode(',', BinanceConstants::SUPPORTED_PAIRS),
            'side'               => 'required|string|in:' . implode(',', array_column(BotSide::cases(), 'value')),
            'price_lower'        => 'required|numeric|min:0',
            'price_upper'        => 'required|numeric|gt:price_lower',
            'grid_count'         => 'required|integer|min:' . GridConstants::MIN_GRIDS . '|max:' . GridConstants::MAX_GRIDS,
            'investment'         => 'required|numeric|min:' . GridConstants::MIN_INVESTMENT,
            'leverage'           => 'required|integer|min:' . GridConstants::MIN_LEVERAGE . '|max:' . GridConstants::MAX_LEVERAGE,
            'slippage'           => 'nullable|numeric|min:0|max:5',
            'stop_loss_price'    => 'nullable|numeric|min:0',
            'take_profit_price'  => 'nullable|numeric|min:0',
            'grid_mode'                    => 'nullable|string|in:arithmetic,geometric',
            'drawdown_mode'                => 'nullable|string|in:peak_equity_drawdown,initial_capital_loss',
            'soft_guard_drawdown_pct'      => 'nullable|numeric|min:0|max:100',
            'hard_guard_drawdown_pct'      => 'nullable|numeric|min:0|max:100',
            'hard_guard_action'            => 'nullable|string|in:stop_bot_only,close_position_and_stop,pause_and_rebuild,notify_only',
            'reentry_enabled'              => 'nullable|boolean',
            'reentry_cooldown_minutes'     => 'nullable|integer|min:5|max:1440',
        ];
    }
}
