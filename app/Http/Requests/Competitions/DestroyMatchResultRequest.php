<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMatchResultRequest extends FormRequest
{
    /**
     * Dezelfde statusguard als bij invoeren en corrigeren: alleen op een
     * actieve competitie.
     *
     * Wissen zonder uitslag is geen kwestie van rechten maar van een verzoek
     * dat niet klopt, dus een 422: er staat niets om te wissen.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        /** @var CompetitionMatch $match */
        $match = $this->route('match');

        abort_unless($competition->status->allowsResultChanges(), 403);
        abort_unless($match->isPlayed(), 422);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
