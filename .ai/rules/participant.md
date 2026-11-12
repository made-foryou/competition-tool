---
paths:
    - 'resources/js/pages/participant/**'
---

# Participant

## Deelnemer ziet alleen eigen wedstrijden; staat, eerstvolgende en tellers komen van de server

Besluit Menno bij #59: een deelnemer ziet uitsluitend zijn eigen wedstrijden, geen speelschema of losse uitslagen van anderen. De stand (#58) is wel volledig zichtbaar, zie hieronder. Alle wedstrijdrijen voor het deelnemersgedeelte komen uit `App\Concerns\SummarizesParticipantMatches`. Daar staan de score vanuit de kijker (`own_score` eerst), `state` (upcoming / awaiting_result / unscheduled / not_played / played), `is_next` en `counts`. Leid die nooit opnieuw af in React, want dashboard en `/{slug}/matches` moeten dezelfde regel volgen.

Een ingeplande wedstrijd met een voorbije speeldag en zonder uitslag is `awaiting_result` en nooit de eerstvolgende. Op een afgeronde competitie heet openstaand `not_played`. Een niet-gekoppelde beheerder krijgt `isParticipant: false` en de wedstrijdprop `null` (niet deferred). "Vandaag" volgt de app-tijdzone (UTC), zie #33.

## Deelnemer ziet de volledige stand; eigen regel en samenvatting komen van de server

Besluit Menno bij #58: deelnemers zien de hele stand, met uitsluitend `display_name`, nooit de naam achter een nickname en nooit een e-mailadres. Het dashboard toont de top 3 plus de eigen regel als die daarbuiten valt, met een link naar `/{slug}/standings`.

`is_viewer` (bepaald op user-id), `viewer_row`, `has_gap` en `total` komen uit `SummarizesStandings::standingsSummary()`. Leid ze niet af in React.

Anders dan `matchOverview` zijn de stand-props altijd deferred, ook voor een niet-gekoppelde beheerder. Die ziet de stand zonder eigen regel, tenzij hij als vertrokken speler zelf uitslagen heeft.

Zonder gespeelde wedstrijden toon je `StandingsEmptyState` en geen rijen met nullen.
