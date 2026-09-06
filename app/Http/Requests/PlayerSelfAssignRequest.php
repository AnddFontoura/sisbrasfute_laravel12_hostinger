<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayerSelfAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game_position_id' => 'required|integer|exists:game_positions,id',
            'payment_method' => 'sometimes|in:wallet,pix,boleto',
            // Payer data required for boleto issuance.
            'payer_name' => 'required_if:payment_method,boleto|string',
            'payer_document' => 'required_if:payment_method,boleto|string',
            'payer_cep' => 'sometimes|nullable|string',
            'payer_address' => 'sometimes|nullable|string',
            'payer_city' => 'sometimes|nullable|string',
            'payer_uf' => 'sometimes|nullable|string|size:2',
        ];
    }
}
