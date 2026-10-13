<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourtReservationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // startTime/endTime are validated as HH:MM (date_format:H:i, no seconds).
        // The service widens them to HH:MM:SS before the slot re-check and insert
        // (design A1 Option A) — do NOT widen in prepareForValidation().
        return [
            'reservationDate' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'customerName' => 'required|string|max:254',
            'description' => 'nullable|string|max:2000',
            'priceCents' => 'required|integer|min:0',
            'isMensalista' => 'boolean',
        ];
    }
}
