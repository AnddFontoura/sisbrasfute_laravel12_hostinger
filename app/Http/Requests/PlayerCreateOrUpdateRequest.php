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
            'playerCityId' => 'nullable|exists:cities,id',
            'playerName' => 'nullable|string|max:255',
            'playerNickName' => 'nullable|string|max:255',
            'playerPositions' => 'nullable|array',
            'playerModalities' => 'nullable|array',
            'playerGender' => 'nullable|string',
            'playerBirthdate' => 'nullable|date',
            'playerHeight' => 'nullable|numeric|between:0,249.99',
            'playerWeight' => 'nullable|numeric|between:0,199.99',
            'playerFootSize' => 'nullable|numeric|between:15,50',
            'playerGloveSize' => 'nullable|numeric|between:5,15',
            'playerUniformSize' => 'nullable|string|max:3',
            'playerPhoto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'social' => 'nullable|array',
            'playerStatus' => 'nullable|in:0,1,true,false',
            'playerYoutube' => 'nullable|string|max:255',
            'playerTiktok' => 'nullable|string|max:255',
            'playerInstagram' => 'nullable|string|max:255',
            'playerX' => 'nullable|string|max:255',
            'playerKwaii' => 'nullable|string|max:255',
            'playerFacebook' => 'nullable|string|max:255',
            'playerGDA' => 'nullable|string|max:255',
            'playerBirthCity' => 'nullable|integer',
            'removePhoto' => 'nullable|string',
        ];
    }
}
