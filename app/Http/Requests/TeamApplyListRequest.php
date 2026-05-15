<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamApplyListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gamePositionId' => 'nullable|integer|exists:game_positions,id'
        ];
    }
}
