<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesMatchDay;
use App\Http\Requests\Competitions\StoreMatchDayRequest;
use App\Http\Requests\Competitions\UpdateMatchDayRequest;
use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MatchDayController extends Controller
{
    use SummarizesMatchDay;

    public function store(StoreMatchDayRequest $request, Competition $competition): RedirectResponse
    {
        $matchDay = DB::transaction(function () use ($request, $competition): MatchDay {
            /** @var MatchDay $matchDay */
            $matchDay = $competition->matchDays()->create($request->safe()->except('field_count'));

            $matchDay->fields()->createMany(
                collect(range(1, $request->integer('field_count')))
                    ->map(fn (int $position): array => [
                        'name' => __('Field :number', ['number' => $position]),
                        'position' => $position,
                    ])
                    ->all(),
            );

            return $matchDay;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day added.')]);

        return back();
    }

    public function edit(Competition $competition, MatchDay $matchDay): Response
    {
        return Inertia::render('competitions/match-days/edit', [
            'competition' => [
                'id' => $competition->id,
                'name' => $competition->name,
                'starts_at' => $competition->starts_at->toDateString(),
                'ends_at' => $competition->ends_at?->toDateString(),
            ],
            'matchDay' => $this->matchDayProps($matchDay),
            'fields' => $matchDay->fields()
                ->get()
                ->map(fn (MatchDayField $field): array => [
                    'id' => $field->id,
                    'name' => $field->name,
                    'position' => $field->position,
                ])
                ->all(),
        ]);
    }

    public function update(UpdateMatchDayRequest $request, Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day updated.')]);

        return back();
    }

    public function destroy(Competition $competition, MatchDay $matchDay): RedirectResponse
    {
        $matchDay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Match day removed.')]);

        return redirect()->route('competitions.edit', $competition);
    }
}
