<?php

namespace App\Http\Controllers\Participant;

use App\Concerns\SummarizesMatchDay;
use App\Concerns\SyncsAvailability;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Participant\StoreCompetitionRegistrationRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deelnemers melden zich hier zelf aan voor een competitie: account aanmaken,
 * koppelen en meteen de beschikbaarheid per speeldag vastleggen.
 */
class CompetitionRegistrationController extends Controller
{
    use SummarizesMatchDay;
    use SyncsAvailability;

    public function show(Request $request, Competition $competition): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $competition->participants()->whereKey($user->id)->exists()) {
            return redirect()->route('competition.dashboard', $competition);
        }

        return Inertia::render('auth/competition-register', [
            'competitionName' => $competition->name,
            'competitionSlug' => $competition->slug,
            'authenticated' => $user !== null,
            'matchDays' => $competition->matchDays()
                ->get()
                ->map(fn (MatchDay $matchDay): array => $this->matchDayProps($matchDay))
                ->all(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(StoreCompetitionRegistrationRequest $request, Competition $competition): RedirectResponse
    {
        $existing = $request->user();

        $user = DB::transaction(function () use ($request, $competition, $existing): User {
            $user = $existing ?? $this->createParticipant($request);

            $competition->participants()->syncWithoutDetaching([$user->id]);

            $this->syncAvailability($user, $competition, $request->validated('match_days') ?? []);

            return $user;
        });

        if ($existing === null) {
            Auth::login($user);

            $request->session()->regenerate();
            $request->session()->put('auth.password_confirmed_at', time());
        }

        return redirect()->route('competition.dashboard', $competition);
    }

    /**
     * Maakt het deelnemersaccount aan. Rol en e-mailverificatie staan niet in
     * de fillable-lijst, vandaar forceFill -- zelfde patroon als bij het
     * accepteren van een uitnodiging.
     */
    protected function createParticipant(StoreCompetitionRegistrationRequest $request): User
    {
        $user = User::create($request->safe()->only(['name', 'nickname', 'email', 'password']));

        $user->forceFill([
            'email_verified_at' => now(),
            'role' => UserRole::Participant,
        ])->save();

        return $user;
    }
}
