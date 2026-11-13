<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesAvailability;
use App\Models\Competition;
use App\Models\MatchDay;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionAvailabilityExportController extends Controller
{
    use SummarizesAvailability;

    /**
     * Tekens waarmee een spreadsheet een celwaarde als formule opvat.
     */
    private const array FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Levert de beschikbaarheidsmatrix van een competitie als CSV-download.
     *
     * De inhoud komt uit `availabilityRows()`, dezelfde bron als de matrix in
     * de beheerinterface. Zou de export zijn eigen query doen, dan kunnen CSV
     * en scherm bij een latere wijziging ongemerkt uit elkaar gaan lopen,
     * terwijl de beheerder juist op basis van dit bestand het speelschema
     * inplant.
     *
     * Puntkomma als scheidingsteken plus een UTF-8 BOM vooraan: Excel met
     * Nederlandse regio-instellingen gebruikt de puntkomma als lijstscheiding
     * en gokt zonder BOM op een 8-bits codepagina, waardoor namen met
     * accenten of een 'ij' uit een andere bron verminkt in beeld komen. Met
     * beide is het bestand met dubbelklikken direct goed leesbaar.
     *
     * Eén regel, gelijk aan de matrix op het scherm en aan de alleen-lezenlijst
     * van de deelnemer: een vinkje is "Beschikbaar", ook als de inzending niet
     * meer actueel is. Een niet-aangevinkte speeldag is "Nog niet ingevuld" als
     * de deelnemer nog nooit heeft ingediend of de speeldag na zijn laatste
     * inzending is aangemaakt of naar een andere datum verzet
     * (`MatchDay::isNewSince()`, via `unanswered_match_day_ids`). Elke andere
     * lege speeldag is "Niet beschikbaar": daar heeft de deelnemer eerder
     * bewust nee gezegd. De totaalrij telt alle vinkjes.
     */
    public function __invoke(Competition $competition): StreamedResponse
    {
        $matchDays = $competition->matchDays()->get();
        $rows = $this->availabilityRows($competition);

        $fileName = sprintf('beschikbaarheid-%s-%s.csv', $competition->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($matchDays, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Kon de uitvoerstroom voor de CSV-export niet openen.');
            }

            // De BOM gaat er rauw in: fputcsv() zou hem als veldwaarde
            // aanhalingstekens kunnen geven en dan leest Excel hem als tekst.
            fwrite($handle, "\xEF\xBB\xBF");

            // format() in plaats van translatedFormat(): de kop is puur
            // numeriek, dus vertalen levert niets op en houdt de kolom
            // machine-leesbaar voor wie het bestand doorrekent.
            $this->writeRow($handle, [
                __('Name'),
                ...$matchDays->map(fn (MatchDay $matchDay): string => sprintf(
                    '%s %s – %s',
                    $matchDay->date->format('d-m-Y'),
                    $matchDay->starts_at,
                    $matchDay->ends_at,
                ))->all(),
            ]);

            foreach ($rows as $row) {
                $this->writeRow($handle, [
                    $row['name'],
                    ...$matchDays->map(function (MatchDay $matchDay) use ($row): string {
                        if (in_array($matchDay->id, $row['match_day_ids'], true)) {
                            return __('Available');
                        }

                        return in_array($matchDay->id, $row['unanswered_match_day_ids'], true)
                            ? __('Not filled in yet')
                            : __('Not available');
                    })->all(),
                ]);
            }

            // Elk vinkje telt mee, ook bij een niet-actuele inzending: dezelfde
            // regel als de cellen hierboven, de matrix op het scherm en de
            // planner.
            $this->writeRow($handle, [
                __('Available count'),
                ...$matchDays->map(fn (MatchDay $matchDay): string => (string) count(array_filter(
                    $rows,
                    fn (array $row): bool => in_array($matchDay->id, $row['match_day_ids'], true),
                )))->all(),
            ]);

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Schrijft één CSV-regel met puntkomma als scheidingsteken.
     *
     * `escape: ''` schakelt het niet-standaard backslash-escapen van PHP uit
     * en is sinds PHP 8.4 verplicht om expliciet mee te geven; zonder de
     * parameter volgt een deprecation-melding die midden in de
     * download-stream terecht zou komen.
     *
     * Elke waarde gaat langs `neutralizeFormula()`, zodat de bescherming
     * centraal staat en ook voor kolommen geldt die hier later bij komen.
     *
     * @param  resource  $handle
     * @param  list<string>  $values
     */
    private function writeRow($handle, array $values): void
    {
        fputcsv($handle, array_map($this->neutralizeFormula(...), $values), separator: ';', escape: '');
    }

    /**
     * Zet een enkele quote voor waarden die een spreadsheet als formule zou
     * uitvoeren.
     *
     * Excel en LibreOffice voeren een cel die begint met `=`, `+`, `-`, `@`,
     * een tab of een carriage return uit als formule zodra het bestand wordt
     * geopend. De namen in deze export komen uit `display_name` en kiest de
     * deelnemer zelf; een naam als `=HYPERLINK("https://...")` zou dus bij de
     * beheerder tot uitvoering komen. De export is juist voor Excel bedoeld,
     * dus dat is een reëel pad van deelnemer naar beheerder.
     *
     * De apostrof vooraan dwingt de cel naar tekst zonder de weergegeven
     * waarde te veranderen: de beheerder ziet gewoon de naam die de deelnemer
     * heeft ingesteld.
     */
    private function neutralizeFormula(string $value): string
    {
        if ($value === '' || ! in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return $value;
        }

        return "'".$value;
    }
}
