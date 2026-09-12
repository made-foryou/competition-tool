<?php

namespace App\Http\Requests\Participant;

use App\Models\Competition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        return [
            'match_days' => ['nullable', 'array'],
            'match_days.*' => [
                'integer',
                'distinct',
                Rule::exists('match_days', 'id')->where('competition_id', $competition->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_days.*.exists' => __('Choose a match day of this competition.'),
        ];
    }
}
