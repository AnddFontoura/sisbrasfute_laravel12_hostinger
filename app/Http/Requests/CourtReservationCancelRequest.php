<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourtReservationCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // scope arrives as a query param; default to 'one' when absent.
        $this->merge([
            'scope' => $this->query('scope', $this->input('scope', 'one')),
        ]);
    }

    public function rules(): array
    {
        return [
            'scope' => 'nullable|in:one,future',
        ];
    }
}
