<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use App\Models\MatchDay;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatchDayRequest extends FormRequest
{
    /**
     * Browsers mogen seconden meesturen (`09:00:30`); de kolom en de
     * validatieregel werken met `H:i`.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_at' => substr((string) $this->input('starts_at'), 0, 5),
            'ends_at' => substr((string) $this->input('ends_at'), 0, 5),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        $dateRules = [
            'required',
            'date',
            'after_or_equal:'.$competition->starts_at->toDateString(),
        ];

        if ($competition->ends_at !== null) {
            $dateRules[] = 'before_or_equal:'.$competition->ends_at->toDateString();
        }

        return [
            'date' => $dateRules,
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'field_count' => ['required', 'integer', 'min:1', 'max:'.MatchDay::MAX_FIELDS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => __('The match day must fall within the competition period.'),
            'date.before_or_equal' => __('The match day must fall within the competition period.'),
            'ends_at.after' => __('The end time must be after the start time.'),
            'field_count.min' => __('Choose between 1 and :max fields.', ['max' => MatchDay::MAX_FIELDS]),
            'field_count.max' => __('Choose between 1 and :max fields.', ['max' => MatchDay::MAX_FIELDS]),
        ];
    }
}
