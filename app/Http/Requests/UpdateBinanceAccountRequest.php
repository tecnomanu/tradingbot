<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBinanceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'      => 'sometimes|string|max:255',
            'api_key'    => 'sometimes|string|min:10',
            'api_secret' => 'sometimes|string|min:10',
            'is_testnet' => 'sometimes|boolean',
            'is_active'  => 'sometimes|boolean',
        ];
    }
}
