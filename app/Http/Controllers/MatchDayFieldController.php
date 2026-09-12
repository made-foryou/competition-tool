<?php

namespace App\Http\Controllers;

use App\Http\Requests\Competitions\StoreMatchDayFieldRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use Illuminate\Http\RedirectResponse;

class MatchDayFieldController extends Controller
{
    public function store(StoreMatchDayFieldRequest $request, Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->fields()->create([
            'name' => $request->string('name')->toString(),
            'position' => (int) $matchDay->fields()->max('position') + 1,
        ]);

        return back();
    }

    public function destroy(Competition $competition, MatchDay $matchDay, MatchDayField $field): RedirectResponse
    {
        $field->delete();

        return back();
    }
}
