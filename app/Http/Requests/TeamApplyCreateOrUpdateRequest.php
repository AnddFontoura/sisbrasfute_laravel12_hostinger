<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamApplyCreateOrUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teamId' => 'required|exists:teams,id',
            'gamePositionId' => 'required|exists:game_positions,id',
        ];
    }
}
