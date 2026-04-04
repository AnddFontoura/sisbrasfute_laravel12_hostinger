<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayerCreateOrUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'playerCityId' => 'required|exists:cities,id',
            'playerName' => 'required|string|max:255',
            'playerNickName' => 'required|string|max:255',
            'playerPositions' => 'nullable|array',
            'playerModalities' => 'nullable|array',
            'playerGender' => 'nullable|string',
            'playerBirthdate' => 'nullable|date',
            'playerHeight' => 'nullable|numeric|between:0,249.99',
            'playerWeight' => 'nullable|numeric|between:0,199.99',
            'playerFootSize' => 'nullable|numeric|between:15,50',
            'playerGloveSize' => 'nullable|numeric|between:5,15',
            'playerUniformSize' => 'nullable|string|max:3',
            'playerPhoto' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'social' => 'nullable|array',
            'playerStatus' => 'required|boolean',
        ];
    }
}
