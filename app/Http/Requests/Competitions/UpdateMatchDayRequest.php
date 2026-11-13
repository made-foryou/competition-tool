<?php

namespace App\Http\Requests\Competitions;

class UpdateMatchDayRequest extends StoreMatchDayRequest
{
    /**
     * Speelvelden worden na het aanmaken los beheerd, niet via dit formulier.
     *
     * `original_date` is de datum die de beheerder op de bewerkpagina zag.
     * `UpdateMatchDay` weigert het opslaan als de speeldag intussen verzet
     * is, zodat een verouderde pagina hem niet stil terugzet.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['field_count']);

        $rules['original_date'] = ['required', 'date_format:Y-m-d'];

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

    /**
     * De datum zoals de beheerder hem op de bewerkpagina zag (`Y-m-d`).
     */
    public function originalDate(): string
    {
        return $this->safe()->string('original_date')->toString();
    }
}
