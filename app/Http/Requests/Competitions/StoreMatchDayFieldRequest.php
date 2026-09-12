<?php

namespace App\Http\Requests\Competitions;

use App\Models\MatchDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatchDayFieldRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var MatchDay $matchDay */
        $matchDay = $this->route('matchDay');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('match_day_fields', 'name')->where('match_day_id', $matchDay->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This field name already exists for this match day.'),
        ];
    }
}
