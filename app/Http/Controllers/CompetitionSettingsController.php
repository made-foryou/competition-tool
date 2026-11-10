<?php

namespace App\Http\Controllers;

use App\Actions\Competitions\UpdateCompetitionSettings;
use App\Http\Requests\Competitions\UpdateCompetitionSettingsRequest;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitionSettingsController extends Controller
{
    public function __invoke(UpdateCompetitionSettingsRequest $request, Competition $competition, UpdateCompetitionSettings $updateCompetitionSettings): RedirectResponse
    {
        $updateCompetitionSettings->handle($competition, $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Planning settings saved.')]);

        return redirect()->route('competitions.edit', $competition);
    }
}
