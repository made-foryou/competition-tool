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
     * Het format afdwingen: precies één speler heeft het aantal games om te
     * winnen. Dat sluit zowel een gelijkspel (beide op dat aantal) uit als
     * een wedstrijd zonder winnaar (geen van beiden).
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
                $gamesToWin = $settings->gamesToWin();

                $first = (int) $this->input('first_player_score');
                $second = (int) $this->input('second_player_score');

                if (($first === $gamesToWin) === ($second === $gamesToWin)) {
                    $validator->errors()->add('score', __('In a best-of-:best_of match the winner has exactly :games games.', [
                        'best_of' => $settings->bestOf,
                        'games' => $gamesToWin,
                    ]));
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
            '*.required' => __('Enter a score.'),
            '*.integer' => __('Enter a whole number.'),
            '*.min' => $outOfRange,
            '*.max' => $outOfRange,
        ];
    }

    private function competition(): Competition
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        return $competition;
    }
}
