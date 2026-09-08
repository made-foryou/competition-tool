<?php

namespace App\Concerns;

use App\Enums\CompetitionStatus;
use App\Models\User;

/**
 * Bepaalt de rolafhankelijke bestemming na inloggen: admins naar het
 * beheer-dashboard, deelnemers naar hun meest recente actieve competitie
 * (of de "geen competitie"-pagina als die er niet is). Wordt gedeeld door
 * de login-responseklassen voor zowel de Fortify- als de passkey-pipeline,
 * zodat beide precies hetzelfde gedrag hebben.
 */
trait DeterminesLoginDestination
{
    protected function defaultUrlFor(?User $user): string
    {
        if ($user === null || $user->isAdmin()) {
            return route('dashboard');
        }

        $competition = $user->competitions()
            ->where('status', CompetitionStatus::Active)
            ->orderByDesc('starts_at')
            ->first();

        return $competition !== null
            ? route('competition.dashboard', $competition)
            : route('competition.none');
    }
}
