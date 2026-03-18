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
            'grid_mode'         => 'nullable|string|in:arithmetic,geometric',
        ];
    }
}
