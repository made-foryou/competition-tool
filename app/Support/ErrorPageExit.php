<?php

namespace App\Support;

use App\Concerns\DeterminesLoginDestination;
use App\Models\User;

/**
 * De weg terug op de foutpagina. Een gast kan alleen naar de startpagina:
 * defaultUrlFor() stuurt hem naar het admin-dashboard, en dat is achter
 * EnsureUserIsAdmin de volgende 403 -- precies de lus die een foutpagina
 * hoort te doorbreken.
 *
 * Let op: op een url die op geen enkele route matcht draait de
 * web-middlewaregroep niet, dus is de sessie niet gestart en is $user daar
 * altijd null -- ook voor wie ingelogd is. Die bezoeker krijgt dan de
 * startpagina als uitweg. Dat is een veilige bestemming, geen lus; het
 * alternatief (een Route::fallback in de web-groep) zou EnsureAvailability-
 * IsSubmitted over elke typefout laten lopen en een 404 in een redirect naar
 * het beschikbaarheidsformulier veranderen.
 */
class ErrorPageExit
{
    use DeterminesLoginDestination;

    /**
     * @return array{returnUrl: string, returnLabel: string}
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [
                'returnUrl' => route('home'),
                'returnLabel' => __('Back to home'),
            ];
        }

        return [
            'returnUrl' => $this->defaultUrlFor($user),
            'returnLabel' => __('Go to your competitions'),
        ];
    }
}
