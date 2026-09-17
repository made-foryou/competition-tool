<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use App\Models\CompetitionMatch;
use Illuminate\Foundation\Http\FormRequest;

class PinMatchRequest extends FormRequest
{
    /**
     * Dezelfde twee grenzen als bij verplaatsen: bijsturen kan alleen op een
     * actieve competitie, en een gespeelde wedstrijd blijft onaangeroerd.
     *
     * Daarbovenop geldt bij vastzetten (`POST`) dat de wedstrijd een plek moet
     * hebben: vastzetten betekent "laat deze wedstrijd staan waar hij staat",
     * en dat is zinloos zonder speeldag, tafel en begintijd. Losmaken
     * (`DELETE`) mag altijd -- dat maakt alleen `pinned_at` leeg.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        /** @var CompetitionMatch $match */
        $match = $this->route('match');

        abort_unless($competition->status->allowsScheduling(), 403);
        abort_if($match->isPlayed(), 403);

        if ($this->isMethod('post')) {
            abort_unless($match->isScheduled(), 422);
        }

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
