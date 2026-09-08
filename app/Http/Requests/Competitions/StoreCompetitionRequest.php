<?php

namespace App\Http\Requests\Competitions;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCompetitionRequest extends FormRequest
{
    /**
     * De slug wordt altijd server-side afgeleid van de naam.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::notIn(Competition::RESERVED_SLUGS), Rule::unique('competitions', 'slug')],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(CompetitionStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.not_in' => __('This name results in a reserved URL. Choose a different name.'),
            'slug.unique' => __('A competition with this URL already exists. Choose a different name.'),
        ];
    }
}
