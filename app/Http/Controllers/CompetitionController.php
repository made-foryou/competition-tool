<?php

namespace App\Http\Controllers;

use App\Http\Requests\Competitions\StoreCompetitionRequest;
use App\Http\Requests\Competitions\UpdateCompetitionRequest;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('competitions/index', [
            'competitions' => Competition::query()
                ->withCount('participants')
                ->orderByDesc('starts_at')
                ->get()
                ->map(fn (Competition $competition): array => [
                    ...$this->competitionProps($competition),
                    'participants_count' => $competition->participants_count,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('competitions/create');
    }

    public function store(StoreCompetitionRequest $request): RedirectResponse
    {
        $competition = Competition::create($request->validated());

        return redirect()->route('competitions.edit', $competition);
    }

    public function edit(Competition $competition): Response
    {
        return Inertia::render('competitions/edit', [
            'competition' => $this->competitionProps($competition),
            'participants' => $competition->participants()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                ])
                ->all(),
            'pendingInvitations' => $competition->invitations()
                ->pending()
                ->get()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toDateString(),
                ])
                ->all(),
        ]);
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition): RedirectResponse
    {
        $competition->update($request->validated());

        return redirect()->route('competitions.edit', $competition);
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();

        return redirect()->route('competitions.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function competitionProps(Competition $competition): array
    {
        return [
            'id' => $competition->id,
            'name' => $competition->name,
            'slug' => $competition->slug,
            'description' => $competition->description,
            'location' => $competition->location,
            'starts_at' => $competition->starts_at->toDateString(),
            'ends_at' => $competition->ends_at?->toDateString(),
            'status' => $competition->status->value,
        ];
    }
}
