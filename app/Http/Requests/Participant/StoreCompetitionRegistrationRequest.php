<?php

namespace App\Http\Requests\Participant;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompetitionRegistrationRequest extends FormRequest
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    /**
     * Alleen actieve competities nemen inschrijvingen aan. Een 404 in plaats
     * van een 403: voor registratiedoeleinden bestaat een concept- of
     * afgeronde competitie niet, ook niet voor admins -- die koppelen
     * deelnemers via het deelnemersbeheer.
     */
    public function authorize(): bool
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        abort_unless($competition->status === CompetitionStatus::Active, 404);

        return true;
    }

    /**
     * Een leeg nickname-veld komt als lege string binnen; dan moet het NULL
     * worden, anders valt de weergavenaam niet terug op de echte naam.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nickname' => $this->filled('nickname')
                ? trim((string) $this->input('nickname'))
                : null,
        ]);
    }

    /**
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Competition $competition */
        $competition = $this->route('competition');

        $isGuest = $this->user() === null;

        return [
            'name' => [Rule::excludeIf(! $isGuest), ...$this->nameRules()],
            'nickname' => [Rule::excludeIf(! $isGuest), ...$this->nicknameRules()],
            'email' => [Rule::excludeIf(! $isGuest), ...$this->emailRules()],
            'password' => [Rule::excludeIf(! $isGuest), ...$this->passwordRules()],
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
            'email.unique' => __('This email address already has an account. Sign in instead.'),
            'match_days.*.exists' => __('Choose a match day of this competition.'),
        ];
    }
}
