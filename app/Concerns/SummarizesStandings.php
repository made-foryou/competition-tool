<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\Standings\StandingRow;
use App\Support\Standings\Standings;

/**
 * De enige plek die een `Standings` omzet naar Inertia-props, voor beheer en
 * deelnemer samen.
 *
 * Geen rekenwerk: posities, gedeelde plaatsen en volgorde komen uit
 * `Standings`. Zolang er geen uitslag is, zijn de rijen leeg: de UI toont dan
 * een lege staat in plaats van rijen met nullen (besluit #58). Een speler
 * staat er alleen met zijn `display_name` (de nickname, of de naam als er geen
 * nickname is): nooit de naam achter een nickname en nooit een e-mailadres.
 */
trait SummarizesStandings
{
    /**
     * @return array{
     *     points_system: string,
     *     rows: list<array{
     *         player_id: int,
     *         position: int,
     *         shares_position: bool,
     *         player: string,
     *         player_is_participant: bool,
     *         played: int,
     *         won: int,
     *         lost: int,
     *         game_difference: int,
     *         points: int,
     *         is_viewer: bool,
     *     }>,
     * }
     */
    protected function standingsProps(Standings $standings, ?User $viewer = null): array
    {
        return [
            'points_system' => $standings->pointsSystem->value,
            'rows' => $standings->hasResults()
                ? array_map(fn (StandingRow $row): array => $this->standingRowProps($row, $viewer), $standings->rows)
                : [],
        ];
    }

    /**
     * De compacte stand voor het deelnemersdashboard: de top 3 plus de eigen
     * regel van de kijker als die daarbuiten valt.
     *
     * De server bepaalt dit, zodat dashboard en volledige pagina dezelfde
     * regel volgen en React niets afleidt: `viewer_row` is gezet vanaf plaats
     * 4 en `has_gap` als er rijen tussen de top 3 en de kijker zitten. Zonder
     * uitslagen blijft alles leeg en is `total` 0.
     *
     * @return array{
     *     points_system: string,
     *     leaders: list<array{
     *         player_id: int,
     *         position: int,
     *         shares_position: bool,
     *         player: string,
     *         player_is_participant: bool,
     *         played: int,
     *         won: int,
     *         lost: int,
     *         game_difference: int,
     *         points: int,
     *         is_viewer: bool,
     *     }>,
     *     viewer_row: array{
     *         player_id: int,
     *         position: int,
     *         shares_position: bool,
     *         player: string,
     *         player_is_participant: bool,
     *         played: int,
     *         won: int,
     *         lost: int,
     *         game_difference: int,
     *         points: int,
     *         is_viewer: bool,
     *     }|null,
     *     has_gap: bool,
     *     total: int,
     * }
     */
    protected function standingsSummary(Standings $standings, User $viewer): array
    {
        if (! $standings->hasResults()) {
            return [
                'points_system' => $standings->pointsSystem->value,
                'leaders' => [],
                'viewer_row' => null,
                'has_gap' => false,
                'total' => 0,
            ];
        }

        $rows = array_map(
            fn (StandingRow $row): array => $this->standingRowProps($row, $viewer),
            $standings->rows,
        );

        $viewerIndex = null;
        foreach ($rows as $index => $row) {
            if ($row['player_id'] === $viewer->id) {
                $viewerIndex = $index;
                break;
            }
        }

        return [
            'points_system' => $standings->pointsSystem->value,
            'leaders' => array_slice($rows, 0, 3),
            'viewer_row' => $viewerIndex !== null && $viewerIndex >= 3 ? $rows[$viewerIndex] : null,
            'has_gap' => $viewerIndex !== null && $viewerIndex > 3,
            'total' => count($rows),
        ];
    }

    /**
     * @return array{
     *     player_id: int,
     *     position: int,
     *     shares_position: bool,
     *     player: string,
     *     player_is_participant: bool,
     *     played: int,
     *     won: int,
     *     lost: int,
     *     game_difference: int,
     *     points: int,
     *     is_viewer: bool,
     * }
     */
    private function standingRowProps(StandingRow $row, ?User $viewer): array
    {
        return [
            'player_id' => $row->player->id,
            'position' => $row->position,
            'shares_position' => $row->sharesPosition,
            'player' => $row->player->displayName,
            'player_is_participant' => $row->player->isParticipant,
            'played' => $row->played,
            'won' => $row->won,
            'lost' => $row->lost,
            'game_difference' => $row->gameDifference(),
            'points' => $row->points,
            'is_viewer' => $viewer !== null && $row->player->id === $viewer->id,
        ];
    }
}
