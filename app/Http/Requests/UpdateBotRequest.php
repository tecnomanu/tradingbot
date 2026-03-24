<?php

namespace App\Http\Requests;

use App\Constants\GridConstants;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'sometimes|required|string|max:255',
            'price_lower'       => 'sometimes|required|numeric|min:0',
            'price_upper'       => 'sometimes|required|numeric|gt:price_lower',
            'grid_count'        => 'sometimes|required|integer|min:' . GridConstants::MIN_GRIDS . '|max:' . GridConstants::MAX_GRIDS,
            'investment'        => 'sometimes|required|numeric|min:' . GridConstants::MIN_INVESTMENT,
            'leverage'          => 'sometimes|required|integer|min:' . GridConstants::MIN_LEVERAGE . '|max:' . GridConstants::MAX_LEVERAGE,
            'slippage'          => 'nullable|numeric|min:0|max:5',
            'stop_loss_price'   => 'nullable|numeric|min:0',
            'take_profit_price' => 'nullable|numeric|min:0',
            'grid_mode'                    => 'nullable|string|in:arithmetic,geometric',
            'ai_consultation_interval'     => 'sometimes|integer|min:0|max:60',
            'drawdown_mode'                => 'nullable|string|in:peak_equity_drawdown,initial_capital_loss',
            'soft_guard_drawdown_pct'      => 'nullable|numeric|min:0|max:100',
            'hard_guard_drawdown_pct'      => 'nullable|numeric|min:0|max:100',
            'hard_guard_action'            => 'nullable|string|in:stop_bot_only,close_position_and_stop,pause_and_rebuild,notify_only',
            'reentry_enabled'              => 'nullable|boolean',
            'reentry_cooldown_minutes'     => 'nullable|integer|min:5|max:1440',
        ];
    }
}
