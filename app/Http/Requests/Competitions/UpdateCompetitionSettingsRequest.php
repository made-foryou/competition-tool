<?php

namespace App\Http\Requests\Competitions;

use App\Enums\PointsSystem;
use App\Models\Competition;
use App\Support\CompetitionSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompetitionSettingsRequest extends FormRequest
{
    /**
     * Checkboxen sturen geen waarde mee wanneer ze uitstaan; normaliseer naar
     * een boolean zodat de `required`/`boolean`-regel altijd klopt.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('use_pools')) {
            $this->merge(['use_pools' => $this->boolean('use_pools')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limits = CompetitionSettings::limits();

        return [
            'match_duration_minutes' => ['required', 'integer', 'min:'.$limits['match_duration_minutes']['min'], 'max:'.$limits['match_duration_minutes']['max']],
            'buffer_minutes' => ['required', 'integer', 'min:'.$limits['buffer_minutes']['min'], 'max:'.$limits['buffer_minutes']['max']],
            'min_rest_minutes' => ['required', 'integer', 'min:'.$limits['min_rest_minutes']['min'], 'max:'.$limits['min_rest_minutes']['max']],
            'break_duration_minutes' => ['required', 'integer', 'min:'.$limits['break_duration_minutes']['min'], 'max:'.$limits['break_duration_minutes']['max']],
            'use_pools' => ['required', 'boolean'],
            'pool_size' => ['exclude_if:use_pools,false', 'required', 'integer', 'min:'.$limits['pool_size']['min'], 'max:'.$limits['pool_size']['max']],
            'max_matches_per_player_per_day' => ['required', 'integer', 'min:'.$limits['max_matches_per_player_per_day']['min'], 'max:'.$limits['max_matches_per_player_per_day']['max']],
            'best_of' => ['required', 'integer', Rule::in(CompetitionSettings::BEST_OF_OPTIONS)],
            'points_system' => ['required', Rule::enum(PointsSystem::class)],
        ];
    }

    /**
     * Zodra er een uitslag is geregistreerd, liggen het wedstrijdformat en de
     * puntentelling vast: alle uitslagen in een competitie hebben gegarandeerd
     * hetzelfde format en dezelfde telling, anders verschuift het klassement.
     * Dezelfde waarde opnieuw meesturen blijft toegestaan.
     *
     * Dit is de directe veldfout; `UpdateCompetitionSettings` controleert
     * hetzelfde nog eens onder de rijlock.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Competition $competition */
                $competition = $this->route('competition');

                $formatChanged = ! $validator->errors()->has('best_of')
                    && (int) $this->input('best_of') !== $competition->settings->bestOf;
                $pointsSystemChanged = ! $validator->errors()->has('points_system')
                    && (string) $this->input('points_system') !== $competition->settings->pointsSystem->value;

                if (! ($formatChanged || $pointsSystemChanged) || ! $competition->hasResults()) {
                    return;
                }

                if ($formatChanged) {
                    $validator->errors()->add('best_of', __('The match format cannot be changed once a result has been entered.'));
                }

                if ($pointsSystemChanged) {
                    $validator->errors()->add('points_system', __('The points system cannot be changed once a result has been entered.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $limits = CompetitionSettings::limits();

        return [
            'match_duration_minutes.min' => __('Enter a value between :min and :max minutes.', $limits['match_duration_minutes']),
            'match_duration_minutes.max' => __('Enter a value between :min and :max minutes.', $limits['match_duration_minutes']),
            'buffer_minutes.min' => __('Enter a value between :min and :max minutes.', $limits['buffer_minutes']),
            'buffer_minutes.max' => __('Enter a value between :min and :max minutes.', $limits['buffer_minutes']),
            'min_rest_minutes.min' => __('Enter a value between :min and :max minutes.', $limits['min_rest_minutes']),
            'min_rest_minutes.max' => __('Enter a value between :min and :max minutes.', $limits['min_rest_minutes']),
            'break_duration_minutes.min' => __('Enter a value between :min and :max minutes.', $limits['break_duration_minutes']),
            'break_duration_minutes.max' => __('Enter a value between :min and :max minutes.', $limits['break_duration_minutes']),
            'pool_size.min' => __('Choose a pool size between :min and :max.', $limits['pool_size']),
            'pool_size.max' => __('Choose a pool size between :min and :max.', $limits['pool_size']),
            'max_matches_per_player_per_day.min' => __('Enter a value between :min and :max.', $limits['max_matches_per_player_per_day']),
            'max_matches_per_player_per_day.max' => __('Enter a value between :min and :max.', $limits['max_matches_per_player_per_day']),
            'best_of.in' => __('Choose a valid match format.'),
            'points_system.enum' => __('Choose a valid points system.'),
        ];
    }

    public function settings(): CompetitionSettings
    {
        $validated = $this->validated();

        return new CompetitionSettings(
            matchDurationMinutes: (int) $validated['match_duration_minutes'],
            bufferMinutes: (int) $validated['buffer_minutes'],
            minRestMinutes: (int) $validated['min_rest_minutes'],
            breakDurationMinutes: (int) $validated['break_duration_minutes'],
            usePools: (bool) $validated['use_pools'],
            poolSize: isset($validated['pool_size']) ? (int) $validated['pool_size'] : null,
            maxMatchesPerPlayerPerDay: (int) $validated['max_matches_per_player_per_day'],
            bestOf: (int) $validated['best_of'],
            pointsSystem: PointsSystem::from($validated['points_system']),
        );
    }
}
