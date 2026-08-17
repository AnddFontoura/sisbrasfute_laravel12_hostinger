<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchesCreateOrUpdateRequest extends FormRequest
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
            'teamId' => 'required|integer|exists:teams,id',
            'matchType' => 'required|string|in:team_match,friendly_match,championship_match',
            'myTeamIs' => 'nullable|string|in:home,visitor',
            'enemyTeamId' => 'nullable|int',
            'enemyTeamName' => 'nullable|string',
            'cityId' => 'required|int',
            'matchLocation' => 'required|string|min:1|max:1000',
            'myTeamScore' => 'nullable|int',
            'enemyTeamScore' => 'nullable|int',
            'hasPenalties' => 'nullable|int',
            'enemyTeamPenaltyScore' => 'nullable|int',
            'myTeamPenaltyScore' => 'nullable|int',
            'matchSchedule' => 'required|date',
            'teamsCount' => 'nullable|int',
            'playersCount' => 'nullable|int',
            'positions' => 'nullable',
            'indicatePositions' => 'nullable',
            'tagId' => 'nullable|integer|exists:team_tags,id',
            'championshipName' => 'nullable|string|max:254',
        ];
    }
}
