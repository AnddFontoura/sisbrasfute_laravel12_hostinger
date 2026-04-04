<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamFinanceCreateOrUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'matchId' => 'nullable|integer|exists:matches,id',
            'teamPlayerId' => 'nullable|integer|exists:team_players,id',
            'description' => 'nullable|string|min:1', //descricao do pagamento, para controle interno
            'value' => 'required|float', //Valor em float
            'method' => 'required|integer', //Método de pamgamento boleto - cartoa - dinheiro - pix
            'type' => 'required|bool', //tipo de pagamento, se é débito (0) de valor ou crédito (1) de valor
            'origin' => 'required|string', //Origem do pagamento, campo, arbitro, bola, mensalidade, outros
        ];
    }
}
