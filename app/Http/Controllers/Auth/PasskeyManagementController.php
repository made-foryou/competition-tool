<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\DeterminesLoginDestination;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Rolonafhankelijke ingang voor het beheren van passkeys. Wordt gebruikt door
 * de `.well-known/passkey-endpoints`-discovery, zodat besturingssystemen zowel
 * beheerders als deelnemers naar het juiste scherm sturen.
 */
class PasskeyManagementController extends Controller
{
    use DeterminesLoginDestination;

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isAdmin()) {
            return redirect()->route('security.edit');
        }

        $competition = $this->activeCompetitionFor($user);

        return $competition !== null
            ? redirect()->route('competition.settings.security.edit', $competition)
            : redirect()->route('competition.none');
    }
}
