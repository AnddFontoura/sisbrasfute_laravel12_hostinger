<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamCreateOrUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teamCityId' => 'nullable|integer|min:1',
            'teamModalityId' => 'nullable|exists:modalities,id',
            'teamName' => 'nullable|string|min:1|max:254',
            'teamDescription' => 'nullable|string',
            'teamGender' => 'nullable|integer',
            'teamFoundationDate' => 'nullable|date',
            'teamLogo' => 'nullable|image|max:10240',
            'teamBanner' => 'nullable|image|max:10240',
            'teamFacebook' => 'nullable|string',
            'teamInstagram' => 'nullable|string',
            'teamX' => 'nullable|string',
            'teamTiktok' => 'nullable|string',
            'teamYoutube' => 'nullable|string',
            'teamKwai' => 'nullable|string',
            'teamKwaii' => 'nullable|string',
        ];
    }
}
