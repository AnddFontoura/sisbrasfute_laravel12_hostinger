<?php

namespace App\Http\Requests;

use App\Models\Court;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CourtCreateOrUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('courtId') !== null;

        return [
            'courtName' => $isUpdate
                ? 'sometimes|nullable|string|max:254'
                : 'required|string|max:254',
            'courtDescription' => 'nullable|string',
            'courtModalityId' => 'required|integer|exists:modalities,id',
            'courtCityId' => 'required|integer|exists:cities,id',
            'courtAddressLine' => 'nullable|string|max:500',
            'courtSurfaceType' => 'required|in:natural,sintetico,emborrachado,piso',

            'contacts' => 'required|array|min:1',
            'contacts.*.type' => 'required|in:whatsapp,phone,other',
            'contacts.*.value' => 'required|string|max:255',
            'contacts.*.label' => 'nullable|string|max:100',

            'photos' => $isUpdate
                ? 'nullable|array'
                : 'required|array|min:1|max:10',
            'photos.*' => 'image|max:10240',

            'removedPhotoIds' => 'nullable|array',
            'removedPhotoIds.*' => 'integer',

            'pricingRules' => 'nullable|array',
            'pricingRules.*.weekday' => 'integer|between:0,6',
            'pricingRules.*.startTime' => 'required_with:pricingRules.*.endTime|date_format:H:i',
            'pricingRules.*.endTime' => 'date_format:H:i|after:pricingRules.*.startTime',
            'pricingRules.*.priceCents' => 'integer|min:0',

            'scheduleConfigs' => 'nullable|array',
            'scheduleConfigs.*.weekday' => 'integer|between:0,6',
            'scheduleConfigs.*.isActive' => 'boolean',
            'scheduleConfigs.*.startTime' => 'nullable|date_format:H:i',
            'scheduleConfigs.*.endTime' => 'nullable|date_format:H:i',
            'scheduleConfigs.*.reservationDuration' => 'nullable|in:60,90,120',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateScheduleConfigs($validator);
            $this->validatePhotoCountOnUpdate($validator);
        });
    }

    private function validateScheduleConfigs(Validator $validator): void
    {
        $scheduleConfigs = $this->input('scheduleConfigs', []);

        if (!is_array($scheduleConfigs)) {
            return;
        }

        $seenWeekdays = [];

        foreach ($scheduleConfigs as $index => $config) {
            $weekday = $config['weekday'] ?? null;

            if ($weekday !== null) {
                if (in_array($weekday, $seenWeekdays, true)) {
                    $validator->errors()->add(
                        "scheduleConfigs.$index.weekday",
                        __('error.court.duplicate_schedule_weekday')
                    );
                } else {
                    $seenWeekdays[] = $weekday;
                }
            }

            $isActive = filter_var($config['isActive'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!$isActive) {
                continue;
            }

            $startTime = $config['startTime'] ?? null;
            $endTime = $config['endTime'] ?? null;
            $duration = $config['reservationDuration'] ?? null;

            if (!$startTime) {
                $validator->errors()->add("scheduleConfigs.$index.startTime", __('error.court.schedule_window_required'));
            }

            if (!$endTime) {
                $validator->errors()->add("scheduleConfigs.$index.endTime", __('error.court.schedule_window_required'));
            }

            if (!$duration) {
                $validator->errors()->add("scheduleConfigs.$index.reservationDuration", __('error.court.schedule_window_required'));
            }

            if ($startTime && $endTime) {
                $start = $this->minutesFromTime($startTime);
                $end = $this->minutesFromTime($endTime);

                if ($start !== null && $end !== null) {
                    if ($end <= $start) {
                        $validator->errors()->add("scheduleConfigs.$index.endTime", __('error.court.schedule_window_invalid'));
                    } elseif ($duration && ($end - $start) < (int) $duration) {
                        $validator->errors()->add("scheduleConfigs.$index.reservationDuration", __('error.court.schedule_window_invalid'));
                    }
                }
            }
        }
    }

    private function validatePhotoCountOnUpdate(Validator $validator): void
    {
        $courtId = $this->route('courtId');

        if ($courtId === null) {
            return;
        }

        $court = Court::with('photos')->find($courtId);

        if (!$court) {
            return;
        }

        $existingIds = $court->photos->pluck('id')->all();
        $removedIds = array_map('intval', (array) $this->input('removedPhotoIds', []));
        $kept = count(array_diff($existingIds, $removedIds));
        $newUploads = count((array) $this->file('photos', []));

        $total = $kept + $newUploads;

        if ($total < 1 || $total > 10) {
            $validator->errors()->add('photos', __('error.court.photos_range'));
        }
    }

    private function minutesFromTime(string $time): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + ((int) $matches[2]);
    }
}
