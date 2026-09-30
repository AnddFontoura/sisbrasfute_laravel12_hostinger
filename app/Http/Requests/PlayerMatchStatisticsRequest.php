<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlayerMatchStatisticsRequest extends FormRequest
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
            'statistics' => 'required|array|min:1',
            'statistics.*.match_has_player_id' => 'required|integer|exists:match_has_players,id',
            'statistics.*.goals_scored' => 'required|integer|min:0|max:99',
            'statistics.*.goals_conceded' => 'required|integer|min:0|max:99',
            'statistics.*.assists' => 'required|integer|min:0|max:99',
            'statistics.*.yellow_cards' => 'required|integer|min:0|max:99',
            'statistics.*.red_cards' => 'required|integer|min:0|max:99',
            'statistics.*.saves' => 'required|integer|min:0|max:99',
            'statistics.*.fouls_committed' => 'required|integer|min:0|max:99',
            'statistics.*.fouls_suffered' => 'required|integer|min:0|max:99',
        ];
    }

    /**
     * Custom attribute names are resolved from the translation files
     * (lang/{locale}/validation.php under the "attributes" key), so the
     * default validation messages are produced in the request's locale.
     * This keeps the statistics validation fully bilingual without
     * hardcoding messages here.
     */
}
