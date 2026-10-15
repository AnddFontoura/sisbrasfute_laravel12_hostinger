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
            // Id of the specific position slot (matches_has_game_positions.id).
            'match_position_id' => 'required|integer|exists:matches_has_game_positions,id',
            'payment_method' => 'sometimes|in:wallet,pix,boleto,card',
            // Payer data required for boleto/card issuance.
            'payer_name' => 'required_if:payment_method,boleto,card|string',
            'payer_document' => 'required_if:payment_method,boleto,card|string',
            'payer_email' => 'required_if:payment_method,card|nullable|email',
            'payer_phone' => 'sometimes|nullable|string',
            'payer_cep' => 'sometimes|nullable|string',
            'payer_address' => 'sometimes|nullable|string',
            'payer_city' => 'sometimes|nullable|string',
            'payer_uf' => 'sometimes|nullable|string|size:2',
            // Credit card: payment_token comes from the Efí JS SDK (frontend).
            'payment_token' => 'required_if:payment_method,card|string',
            'installments' => 'sometimes|nullable|integer|min:1|max:12',
            // Chosen shirt number (validated against the match uniform in the service).
            'number' => 'sometimes|nullable|integer|min:1|max:999',
        ];
    }
}
