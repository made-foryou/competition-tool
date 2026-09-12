<?php

namespace App\Http\Requests\Competitions;

class UpdateMatchDayRequest extends StoreMatchDayRequest
{
    /**
     * Speelvelden worden na het aanmaken los beheerd, niet via dit formulier.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['field_count']);

        return $rules;
    }
}
