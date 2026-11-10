<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMatchResultRequest extends FormRequest
{
    /**
     * De statusguard voor uitslagen, hier als 403: de beheerder ziet de
     * wedstrijdenlijst gewoon, alleen invoeren en corrigeren zijn dicht
     * zodra de competitie niet actief is.
     *
     * Een gespeelde wedstrijd mag hier wél opnieuw opgeslagen worden: zo
     * werkt corrigeren.
     */
    public function authorize(): bool
    {
        abort_unless($this->competition()->status->allowsResultChanges(), 403);

        return true;
    }

    /**
     * Elke score is het aantal gewonnen games van die speler, dus een geheel
     * getal tussen 0 en het aantal games dat de winnaar nodig heeft.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $gamesToWin = $this->competition()->settings->gamesToWin();

        return [
            'first_player_score' => ['required', 'integer', 'min:0', 'max:'.$gamesToWin],
            'second_player_score' => ['required', 'integer', 'min:0', 'max:'.$gamesToWin],
        ];
    }

    /**
     * Het format afdwingen via `CompetitionSettings::isValidResult()`: precies
     * één speler heeft het aantal games om te winnen. `RecordMatchResult`
     * controleert dezelfde regel nog eens onder de rijlock; deze controle
     * geeft de beheerder de directe veldfout.
     *
     * De fout staat op de aparte key `score` en niet op een van de spelers:
     * hij gaat over het paar, en geen van beide scores is op zichzelf fout.
     * De dialoog toont hem onder beide velden.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['first_player_score', 'second_player_score'])) {
                    return;
                }

                $settings = $this->competition()->settings;

                if (! $settings->isValidResult($this->integer('first_player_score'), $this->integer('second_player_score'))) {
                    $validator->errors()->add('score', $settings->invalidResultMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $outOfRange = __('Enter a score between 0 and :max.', ['max' => $this->competition()->settings->gamesToWin()]);

        return [
            'first_player_score.required' => __('Enter a score.'),
            'first_player_score.integer' => __('Enter a whole number.'),
            'first_player_score.min' => $outOfRange,
            'first_player_score.max' => $outOfRange,
            'second_player_score.required' => __('Enter a score.'),
            'second_player_score.integer' => __('Enter a whole number.'),
            'second_player_score.min' => $outOfRange,
            'second_player_score.max' => $outOfRange,
        ];
    }

    private function competition(): Competition
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        return $competition;
    }
}
