<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchPositionSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game_position_id' => 'required|integer|exists:game_positions,id',
            'team_player_id'   => 'required|integer|exists:team_players,id',
            'price_payed'      => 'nullable|numeric|min:0|max:999999.99',
        ];
    }
}
