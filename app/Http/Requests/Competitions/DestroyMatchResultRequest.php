<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;

class DestroyMatchResultRequest extends FormRequest
{
    /**
     * Dezelfde statusguard als bij invoeren en corrigeren: alleen op een
     * actieve competitie.
     *
     * Wissen zonder uitslag is geen fout: `ClearMatchResult` laat de
     * wedstrijd dan ongemoeid en de beheerder krijgt dezelfde bevestiging.
     * Zo levert een wedstrijd die iemand anders net al wiste geen kale
     * foutmelding op.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        abort_unless($competition->status->allowsResultChanges(), 403);

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
