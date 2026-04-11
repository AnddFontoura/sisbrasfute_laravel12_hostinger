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
            'description' => 'nullable|string|min:1',
            'value' => 'required|float',
            'method' => 'required|integer',
            'type' => 'required|bool',
            'origin' => 'required|string',
        ];
    }
}
