<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'teamId' => 'nullable|integer|exists:teams,id',
            'name' => 'nullable|string|max:255',
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'modality_id' => 'nullable|integer|in:1,2,3,4',
        ];
    }
}
