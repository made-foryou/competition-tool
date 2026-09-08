<?php

namespace App\Http\Requests\Competitions;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompetitionParticipantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userExists = User::query()->where('email', $this->input('email'))->exists();
        $isCreating = $this->input('mode') === 'create';

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'mode' => [Rule::excludeIf($userExists), 'required', Rule::in(['invite', 'create'])],
            'name' => [Rule::excludeIf($userExists || ! $isCreating), 'required', 'string', 'max:255'],
            'password' => [Rule::excludeIf($userExists || ! $isCreating), 'required', 'string', Password::defaults()],
        ];
    }
}
