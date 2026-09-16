<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveMatchRequest extends FormRequest
{
    /**
     * De statusguard van de schrijfroutes, hier als 403: de beheerder ziet de
     * pagina gewoon, alleen het bijsturen is dicht zodra de competitie niet
     * meer actief is.
     *
     * Een gespeelde wedstrijd wordt nooit verplaatst (besluit 6 van het
     * ontwerp): hij houdt zijn plek, bezet die en telt mee voor de rust en het
     * dagmaximum van beide spelers.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        /** @var CompetitionMatch $match */
        $match = $this->route('match');

        abort_unless($competition->status->allowsScheduling(), 403);
        abort_if($match->isPlayed(), 403);

        return true;
    }

    /**
     * Browsers mogen seconden meesturen (`19:00:30`); het slotraster werkt met
     * `H:i`. De ids komen uit een `<select>` en dus als string binnen, terwijl
     * `Rule::exists()` hieronder op de tafel filtert met de speeldag uit dezelfde
     * invoer.
     */
    protected function prepareForValidation(): void
    {
        $matchDayId = $this->input('match_day_id');
        $fieldId = $this->input('match_day_field_id');

        $this->merge([
            'match_day_id' => is_numeric($matchDayId) ? (int) $matchDayId : $matchDayId,
            'match_day_field_id' => is_numeric($fieldId) ? (int) $fieldId : $fieldId,
            'starts_at' => substr((string) $this->input('starts_at'), 0, 5),
        ]);
    }

    /**
     * Alleen de vorm: de speeldag hoort bij deze competitie, de tafel bij die
     * speeldag en de tijd is een kloktijd. De harde randvoorwaarden (bestaat
     * het slot, is iedereen vrij) doet `MoveCompetitionMatch` onder de rijlock.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        return [
            'match_day_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('match_days', 'id')->where('competition_id', $competition->id),
            ],
            'match_day_field_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('match_day_fields', 'id')->where('match_day_id', (int) $this->input('match_day_id')),
            ],
            'starts_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'match_day_id.exists' => __('Choose a match day of this competition.'),
            'match_day_field_id.exists' => __('This field does not belong to the selected match day.'),
        ];
    }
}
