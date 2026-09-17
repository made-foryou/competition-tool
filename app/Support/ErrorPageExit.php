<?php

namespace App\Support;

use App\Concerns\DeterminesLoginDestination;
use App\Models\User;

/**
 * De weg terug op de foutpagina. Een gast kan alleen naar de startpagina:
 * defaultUrlFor() stuurt hem naar het admin-dashboard, en dat is achter
 * EnsureUserIsAdmin de volgende 403 -- precies de lus die een foutpagina
 * hoort te doorbreken.
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
