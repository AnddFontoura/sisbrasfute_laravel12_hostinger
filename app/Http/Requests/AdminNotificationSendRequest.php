<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminNotificationSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['game', 'team', 'system'])],
            'title' => ['required', 'string', 'max:254'],
            'description' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['all', 'users', 'team', 'match'])],
            'userIds' => ['required_if:audience,users', 'array'],
            'userIds.*' => ['integer', 'exists:users,id'],
            'teamId' => ['required_if:audience,team', 'integer', 'exists:teams,id'],
            'matchId' => ['required_if:audience,match', 'integer', 'exists:matches,id'],
        ];
    }
}
