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
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statistics.required' => 'O campo estatísticas é obrigatório.',
            'statistics.array' => 'O campo estatísticas deve ser uma lista.',
            'statistics.min' => 'É necessário informar estatísticas de pelo menos um jogador.',

            'statistics.*.match_has_player_id.required' => 'O campo jogador escalado é obrigatório.',
            'statistics.*.match_has_player_id.integer' => 'O campo jogador escalado deve ser um número inteiro.',
            'statistics.*.match_has_player_id.exists' => 'O jogador escalado informado não existe.',

            'statistics.*.goals_scored.required' => 'O campo gols marcados é obrigatório.',
            'statistics.*.goals_scored.integer' => 'O campo gols marcados deve ser um número inteiro.',
            'statistics.*.goals_scored.min' => 'O campo gols marcados deve ter o valor mínimo de 0.',
            'statistics.*.goals_scored.max' => 'O campo gols marcados deve ter o valor máximo de 99.',

            'statistics.*.goals_conceded.required' => 'O campo gols sofridos é obrigatório.',
            'statistics.*.goals_conceded.integer' => 'O campo gols sofridos deve ser um número inteiro.',
            'statistics.*.goals_conceded.min' => 'O campo gols sofridos deve ter o valor mínimo de 0.',
            'statistics.*.goals_conceded.max' => 'O campo gols sofridos deve ter o valor máximo de 99.',

            'statistics.*.assists.required' => 'O campo assistências é obrigatório.',
            'statistics.*.assists.integer' => 'O campo assistências deve ser um número inteiro.',
            'statistics.*.assists.min' => 'O campo assistências deve ter o valor mínimo de 0.',
            'statistics.*.assists.max' => 'O campo assistências deve ter o valor máximo de 99.',

            'statistics.*.yellow_cards.required' => 'O campo cartões amarelos é obrigatório.',
            'statistics.*.yellow_cards.integer' => 'O campo cartões amarelos deve ser um número inteiro.',
            'statistics.*.yellow_cards.min' => 'O campo cartões amarelos deve ter o valor mínimo de 0.',
            'statistics.*.yellow_cards.max' => 'O campo cartões amarelos deve ter o valor máximo de 99.',

            'statistics.*.red_cards.required' => 'O campo cartões vermelhos é obrigatório.',
            'statistics.*.red_cards.integer' => 'O campo cartões vermelhos deve ser um número inteiro.',
            'statistics.*.red_cards.min' => 'O campo cartões vermelhos deve ter o valor mínimo de 0.',
            'statistics.*.red_cards.max' => 'O campo cartões vermelhos deve ter o valor máximo de 99.',

            'statistics.*.saves.required' => 'O campo defesas é obrigatório.',
            'statistics.*.saves.integer' => 'O campo defesas deve ser um número inteiro.',
            'statistics.*.saves.min' => 'O campo defesas deve ter o valor mínimo de 0.',
            'statistics.*.saves.max' => 'O campo defesas deve ter o valor máximo de 99.',

            'statistics.*.fouls_committed.required' => 'O campo faltas cometidas é obrigatório.',
            'statistics.*.fouls_committed.integer' => 'O campo faltas cometidas deve ser um número inteiro.',
            'statistics.*.fouls_committed.min' => 'O campo faltas cometidas deve ter o valor mínimo de 0.',
            'statistics.*.fouls_committed.max' => 'O campo faltas cometidas deve ter o valor máximo de 99.',

            'statistics.*.fouls_suffered.required' => 'O campo faltas sofridas é obrigatório.',
            'statistics.*.fouls_suffered.integer' => 'O campo faltas sofridas deve ser um número inteiro.',
            'statistics.*.fouls_suffered.min' => 'O campo faltas sofridas deve ter o valor mínimo de 0.',
            'statistics.*.fouls_suffered.max' => 'O campo faltas sofridas deve ter o valor máximo de 99.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'statistics' => 'estatísticas',
            'statistics.*.match_has_player_id' => 'jogador escalado',
            'statistics.*.goals_scored' => 'gols marcados',
            'statistics.*.goals_conceded' => 'gols sofridos',
            'statistics.*.assists' => 'assistências',
            'statistics.*.yellow_cards' => 'cartões amarelos',
            'statistics.*.red_cards' => 'cartões vermelhos',
            'statistics.*.saves' => 'defesas',
            'statistics.*.fouls_committed' => 'faltas cometidas',
            'statistics.*.fouls_suffered' => 'faltas sofridas',
        ];
    }
}
