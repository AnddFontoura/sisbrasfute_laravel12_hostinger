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
            'teamCityId' => 'required|integer|min:1',
            'teamModalityId' => 'required|exists:modalities,id',
            'teamName' => 'required|string|min:1|max:254',
            'teamDescription' => 'required|string|min:1',
            'teamGender' => 'required|integer',
            'teamFoundationDate' => 'required|date:Y-m-d',
            'teamLogo' => 'nullable|image',
            'teamBanner' => 'nullable|image',
            'teamFacebook' => 'nullable|string',
            'teamInstagram' => 'nullable|string',
            'teamX' => 'nullable|string',
            'teamTiktok' => 'nullable|string',
            'teamYoutube' => 'nullable|string',
            'teamKwai' => 'nullable|string',
        ];
    }
}
