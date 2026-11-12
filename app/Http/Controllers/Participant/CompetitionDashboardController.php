<?php

namespace App\Http\Controllers\Participant;

use App\Actions\Competitions\BuildStandings;
use App\Concerns\SummarizesParticipantMatches;
use App\Concerns\SummarizesStandings;
use App\Concerns\SyncsAvailability;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionDashboardController extends Controller
{
    use SummarizesParticipantMatches;
    use SummarizesStandings;
    use SyncsAvailability;

    public function __invoke(Request $request, Competition $competition, BuildStandings $buildStandings): Response
    {
        $user = $request->user();
        $isParticipant = $competition->hasParticipant($user);
        $matchDays = $this->availabilityProps($user, $competition);

        return Inertia::render('participant/dashboard', [
            'competition' => [
                'name' => $competition->name,
                'slug' => $competition->slug,
                'status' => $competition->status->value,
                'description' => $competition->description,
                'location' => $competition->location,
                'starts_at' => $competition->starts_at->toDateString(),
                'ends_at' => $competition->ends_at?->toDateString(),
            ],
            'participants' => $competition->participants()
                ->orderByRaw('COALESCE(NULLIF(users.nickname, ?), users.name)', [''])
                ->get()
                ->map(fn (User $participant): array => [
                    'id' => $participant->id,
                    'name' => $participant->display_name,
                ])
                ->all(),
            'availableMatchDays' => count(array_filter(
                $matchDays,
                fn (array $matchDay): bool => $matchDay['is_available'],
            )),
            'totalMatchDays' => count($matchDays),
            'isParticipant' => $isParticipant,
            'matchOverview' => $isParticipant
                ? Inertia::defer(fn (): array => $this->participantMatchOverview($user, $competition))
                : null,
            'standingsSummary' => Inertia::defer(
                fn (): array => $this->standingsSummary($buildStandings->handle($competition), $user),
                'standings',
            ),
        ]);
    }
}
