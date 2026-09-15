<?php

namespace App\Http\Requests\Competitions;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompetitionParticipantRequest extends FormRequest
{
    /**
     * De beheerder kiest altijd expliciet hoe iemand erbij komt. Welke keuzes
     * gelden hangt af van het e-mailadres: een bestaand account koppel je
     * (`link`), een onbekend adres maak je aan (`create`), en uitnodigen
     * (`invite`) kan in beide gevallen. De front-end vraagt dat vooraf op,
     * maar dat antwoord kan verouderen -- daarom beslist deze regel, en
     * levert een niet-passende keuze een nette validatiefout op.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userExists = User::query()->where('email', $this->input('email'))->exists();
        $isCreating = $this->input('mode') === 'create';

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'mode' => ['required', Rule::in($userExists ? ['invite', 'link'] : ['invite', 'create'])],
            'name' => [Rule::excludeIf($userExists || ! $isCreating), 'required', 'string', 'max:255'],
            'password' => [Rule::excludeIf($userExists || ! $isCreating), 'required', 'string', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.in' => __('This choice does not match the email address. Check the form and try again.'),
        ];
    }
}
