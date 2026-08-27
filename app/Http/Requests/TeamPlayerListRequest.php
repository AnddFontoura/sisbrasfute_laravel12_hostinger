<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamPlayerListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'name' => 'nullable|string|max:255',
            'game_position_id' => 'nullable|integer|exists:game_positions,id',
            'tag_id' => 'nullable|integer|exists:team_tags,id',
            'showDeleted' => 'nullable|string',
            'active' => 'nullable|in:true,false,all',
        ];
    }
}
