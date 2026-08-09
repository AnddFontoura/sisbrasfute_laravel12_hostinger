<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamPlayerCreateOrUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'game_position_id' => 'nullable|integer|exists:game_positions,id',
            'uniform_size' => 'nullable|string|max:10',
            'photo' => 'nullable|string',
            'number' => 'nullable|integer',
            'height' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'foot_size' => 'nullable|numeric',
            'glove_size' => 'nullable|string|max:10',
            'birthdate' => 'nullable|date',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:team_tags,id',
        ];
    }
}
