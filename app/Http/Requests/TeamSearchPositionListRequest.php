<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamSearchPositionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game_position_id' => 'sometimes|integer|exists:game_positions,id',
            'name' => 'sometimes|string|min:1|max:255',
            'nickname' => 'sometimes|string|min:1|max:255'
        ];
    }
}
