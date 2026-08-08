<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchPositionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_payed' => 'required|numeric|min:0|max:999999.99',
        ];
    }
}
