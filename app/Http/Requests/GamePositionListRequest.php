<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GamePositionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teamId' => 'nullable|integer|exists:teams,id'
        ];
    }
}
