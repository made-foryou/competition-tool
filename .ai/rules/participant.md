---
paths:
    - 'resources/js/pages/participant/**'
---

# Participant

## Deelnemer ziet alleen eigen wedstrijden; staat, eerstvolgende en tellers komen van de server

Besluit Menno bij #59: een deelnemer ziet uitsluitend zijn eigen wedstrijden, geen speelschema of uitslagen van anderen (dat hoort bij #58 of een eigen issue). Alle wedstrijdrijen voor het deelnemersgedeelte komen uit `App\Concerns\SummarizesParticipantMatches`. Daar staan de score vanuit de kijker (`own_score` eerst), `state` (upcoming / awaiting_result / unscheduled / not_played / played), `is_next` en `counts`. Leid die nooit opnieuw af in React, want dashboard en `/{slug}/matches` moeten dezelfde regel volgen.

Een ingeplande wedstrijd met een voorbije speeldag en zonder uitslag is `awaiting_result` en nooit de eerstvolgende. Op een afgeronde competitie heet openstaand `not_played`. Een niet-gekoppelde beheerder krijgt `isParticipant: false` en de wedstrijdprop `null` (niet deferred). "Vandaag" volgt de app-tijdzone (UTC), zie #33.
