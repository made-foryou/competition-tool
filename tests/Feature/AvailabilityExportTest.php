<?php

use App\Models\Competition;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;

/**
 * De CSV ontleed als rijen met kolommen. Het ontleden met een puntkomma legt
 * meteen vast dat dát het scheidingsteken is: met een komma zou elke regel één
 * veld opleveren en falen de kolomassertions.
 *
 * @return list<list<string>>
 */
function availabilityExportRows(string $content): array
{
    $withoutBom = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

    return array_map(
        fn (string $line): array => str_getcsv($line, ';', '"', ''),
        preg_split("/\r\n|\n/", trim($withoutBom)) ?: [],
    );
}

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

test('the export is a csv download named after the competition and the current day', function () {
    $this->travelTo('2026-09-13 08:00:00');

    $competition = Competition::factory()->create(['slug' => 'voorjaarstoernooi']);

    $this->get(route('competitions.availability.export', $competition))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('beschikbaarheid-voorjaarstoernooi-2026-09-13.csv');
});

test('the export starts with a utf-8 byte order mark', function () {
    $competition = Competition::factory()->create();

    $content = $this->get(route('competitions.availability.export', $competition))
        ->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF");
});

test('the header row names each match day with its date and time slot', function () {
    $competition = Competition::factory()->create();
    MatchDay::factory()->create([
        'competition_id' => $competition,
        'date' => '2026-10-02',
        'starts_at' => '09:00',
        'ends_at' => '17:00',
    ]);
    MatchDay::factory()->create([
        'competition_id' => $competition,
        'date' => '2026-10-03',
        'starts_at' => '10:30',
        'ends_at' => '16:00',
    ]);

    $rows = availabilityExportRows(
        $this->get(route('competitions.availability.export', $competition))->streamedContent(),
    );

    expect($rows[0])->toBe(['Naam', '02-10-2026 09:00 – 17:00', '03-10-2026 10:30 – 16:00']);
});

test('a participant who submitted is available only on the chosen match day', function () {
    $competition = Competition::factory()->create();
    $firstDay = MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-02']);
    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-03']);

    $participant = User::factory()->participant()->create(['name' => 'Anna']);
    $competition->participants()->attach($participant, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create([
        'match_day_id' => $firstDay,
        'user_id' => $participant,
    ]);

    $rows = availabilityExportRows(
        $this->get(route('competitions.availability.export', $competition))->streamedContent(),
    );

    expect($rows[1])->toBe(['Anna', 'Beschikbaar', 'Niet beschikbaar']);
});

test('a participant who has not submitted shows as not filled in on every match day', function () {
    $competition = Competition::factory()->create();
    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-02']);
    MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-03']);

    $participant = User::factory()->participant()->create(['name' => 'Bob']);
    $competition->participants()->attach($participant);

    $rows = availabilityExportRows(
        $this->get(route('competitions.availability.export', $competition))->streamedContent(),
    );

    expect($rows[1])->toBe(['Bob', 'Nog niet ingevuld', 'Nog niet ingevuld']);
});

test('the last row counts the available participants per match day', function () {
    $competition = Competition::factory()->create();
    $firstDay = MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-02']);
    $secondDay = MatchDay::factory()->create(['competition_id' => $competition, 'date' => '2026-10-03']);

    $anna = User::factory()->participant()->create(['name' => 'Anna']);
    $competition->participants()->attach($anna, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create(['match_day_id' => $firstDay, 'user_id' => $anna]);
    MatchDayAvailability::factory()->create(['match_day_id' => $secondDay, 'user_id' => $anna]);

    $bob = User::factory()->participant()->create(['name' => 'Bob']);
    $competition->participants()->attach($bob, ['availability_submitted_at' => now()]);
    MatchDayAvailability::factory()->create(['match_day_id' => $firstDay, 'user_id' => $bob]);

    $carla = User::factory()->participant()->create(['name' => 'Carla']);
    $competition->participants()->attach($carla);

    $rows = availabilityExportRows(
        $this->get(route('competitions.availability.export', $competition))->streamedContent(),
    );

    expect(end($rows))->toBe(['Aantal beschikbaar', '2', '1']);
});

test('a competition without match days and participants still exports a header and a total row', function () {
    $competition = Competition::factory()->create();

    $rows = availabilityExportRows(
        $this->get(route('competitions.availability.export', $competition))
            ->assertOk()
            ->streamedContent(),
    );

    expect($rows)->toBe([['Naam'], ['Aantal beschikbaar']]);
});

test('a participant cannot export the availability', function () {
    $competition = Competition::factory()->create();
    $participant = User::factory()->participant()->create();
    $competition->participants()->attach($participant);

    $this->actingAs($participant)
        ->get(route('competitions.availability.export', $competition))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $competition = Competition::factory()->create();

    auth()->logout();

    $this->get(route('competitions.availability.export', $competition))
        ->assertRedirect(route('login'));
});
