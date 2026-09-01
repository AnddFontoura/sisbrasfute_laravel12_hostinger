<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminNotificationUpdateRequest extends FormRequest
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
        ];
    }
}
