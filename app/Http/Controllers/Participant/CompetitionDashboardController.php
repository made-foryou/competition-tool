<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionDashboardController extends Controller
{
    public function __invoke(Competition $competition): Response
    {
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
                ->orderBy('name')
                ->get()
                ->map(fn (User $participant): array => [
                    'id' => $participant->id,
                    'name' => $participant->name,
                ])
                ->all(),
        ]);
    }
}
