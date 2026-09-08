<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use Illuminate\Validation\Rule;

class UpdateCompetitionRequest extends StoreCompetitionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = ['required', 'string', 'max:255', Rule::notIn(Competition::RESERVED_SLUGS), Rule::unique('competitions', 'slug')->ignore($this->route('competition'))];

        return $rules;
    }
}
