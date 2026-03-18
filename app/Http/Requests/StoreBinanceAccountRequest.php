<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBinanceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'      => 'required|string|max:255',
            'api_key'    => 'required|string|min:10',
            'api_secret' => 'required|string|min:10',
            'is_testnet' => 'boolean',
        ];
    }
}
