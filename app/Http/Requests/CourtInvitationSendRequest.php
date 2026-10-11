<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourtInvitationSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:254',
            'role' => 'required|in:manager,employee',
        ];
    }
}
