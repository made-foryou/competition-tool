<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Een leeg nickname-veld komt als lege string binnen; dan moet het NULL
     * worden, anders valt de weergavenaam niet terug op de echte naam.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nickname' => $this->filled('nickname')
                ? trim((string) $this->input('nickname'))
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }
}
