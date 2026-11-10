---
paths:
    - 'app/**'
---

# App

## Aanmelden voor een competitie kan alleen als die actief is

Eén lijn voor alle aanmeldroutes: `CompetitionStatus::allowsSignUp()` (alleen `Active`) bepaalt of iemand zich voor een competitie kan aanmelden — via de publieke inschrijfpagina én via een uitnodiging, met of zonder bestaand account. Bouw hier geen uitzondering per route omheen; dat verschil bestond eerder wel (uitnodigingen mochten op `Draft`) en is bewust weggehaald.

Gevolg: een uitnodiging die tijdens de conceptfase is verstuurd, werkt pas zodra de competitie actief staat. De genodigde krijgt zolang de `upcoming`-staat op de uitnodigingspagina.

Valt hier NIET onder: een beheerder die zelf een deelnemer koppelt (`CompetitionParticipantController`). Die bouwt de deelnemerslijst op, ook tijdens de conceptfase, en heeft daarom geen statusguard.

## Uitslagen wijzigen alleen op een actieve competitie; het format vergrendelt na de eerste uitslag

`CompetitionStatus::allowsResultChanges()` (alleen `Active`) is de enige regel voor het invoeren, corrigeren én wissen van een uitslag. Afgerond is bevroren: wie na afronden nog iets wil invoeren, zet de competitie eerst terug op Actief. Bouw er geen uitzondering voor `Finished` omheen; dat is bewust afgewogen bij #56.

Het wedstrijdformat (`CompetitionSettings::bestOf`, 1/3/5/7) wordt strikt afgedwongen via `CompetitionSettings::isValidResult()`: precies één speler heeft `gamesToWin()`. Het format ligt vast zodra `Competition::hasResults()` waar is, zodat alle uitslagen in een competitie hetzelfde format hebben (nodig voor het klassement).

Val: de Form Requests lezen de competitie vóór de rijlock. `RecordMatchResult`, `ClearMatchResult` en `UpdateCompetitionSettings` controleren status, format en vergrendeling daarom opnieuw ná `lockForUpdate()` op de competitierij. Een nieuwe schrijfroute op uitslagen of instellingen moet dat ook doen.
