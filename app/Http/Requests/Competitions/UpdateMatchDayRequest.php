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

    /**
     * De gevalideerde datum en tijden, getypeerd voor `UpdateMatchDay`.
     *
     * @return array{date: string, starts_at: string, ends_at: string}
     */
    public function matchDayAttributes(): array
    {
        $validated = $this->safe();

        return [
            'date' => $validated->string('date')->toString(),
            'starts_at' => $validated->string('starts_at')->toString(),
            'ends_at' => $validated->string('ends_at')->toString(),
        ];
    }
}
