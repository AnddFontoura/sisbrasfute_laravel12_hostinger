<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamApplyAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'applicationDecision' => ['required', 'string'],
            'justification' => ['nullable', 'string'],
        ];
    }
}
