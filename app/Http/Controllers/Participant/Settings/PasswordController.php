<?php

namespace App\Http\Controllers\Participant\Settings;

use App\Concerns\SummarizesCompetition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wachtwoord wijzigen binnen het deelnemersgedeelte. Staat los van de
 * beveiligingspagina omdat dit formulier zelf om het huidige wachtwoord
 * vraagt en dus geen extra wachtwoordbevestiging nodig heeft.
 */
class PasswordController extends Controller
{
    use SummarizesCompetition;

    public function edit(Competition $competition): Response
    {
        return Inertia::render('participant/settings/password', [
            'competition' => $this->competitionSummary($competition),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(PasswordUpdateRequest $request, Competition $competition): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('competition.settings.password.edit', $competition);
    }
}
