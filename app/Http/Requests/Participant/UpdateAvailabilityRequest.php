<?php

namespace App\Http\Requests\Participant;

use App\Models\Competition;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRequest extends FormRequest
{
    /**
     * Een 403 en geen 404: de deelnemer kent het bestaan van deze competitie
     * al en mag de pagina gewoon blijven bekijken (die toont zichzelf al
     * read-only) -- alleen het opslaan is dicht. Dat is een ander scenario
     * dan `StoreCompetitionRegistrationRequest`, waar de competitie voor een
     * niet-deelnemer nog niet hoort te bestaan.
     *
     * De deelnemercheck is hier nodig, ook al draait
     * `EnsureUserParticipatesInCompetition` al: die laat elke admin door, wat
     * prima is om mee te kijken, maar een admin die geen deelnemer is zou
     * hier anders weesrijen in `match_day_availabilities` op zijn eigen
     * user_id schrijven, terwijl `updateExistingPivot` voor de ontbrekende
     * koppeling een no-op is.
     *
     * De deelnemercheck staat bewust vóór de statuscheck, zodat de meest
     * specifieke reden leidend is.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        $user = $this->user();

        abort_unless($user instanceof User, 403);
        abort_unless($competition->hasParticipant($user), 403);
        abort_unless($competition->status->allowsAvailabilityChanges(), 403);

        return true;
    }

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
