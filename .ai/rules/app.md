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

## Beschikbaarheid hangt aan deelname, niet aan rol

Wie deelnemer is van een actieve competitie met speeldagen moet beschikbaarheid indienen, ook een beheerder (besluit Menno bij #37). Bouw dus geen rol-uitzondering in `EnsureAvailabilityIsSubmitted` of in `SummarizesAvailability::pendingAvailabilityParticipantsQuery()`: matrix, teller, herinneringen en planner (die "geen rij" als "niet beschikbaar" leest) gaan er allemaal van uit dat elke deelnemer indient. Een beheerder die nergens deelnemer is, wordt niet geraakt.

Val: de 2FA-setup gaat voor. `EnsureAvailabilityIsSubmitted` laat een gebruiker met `User::requiresSecondFactorSetup()` door; zonder die guard ontstaat een redirect-lus tussen `two-factor.setup` en het formulier. Na een eerste inzending landt een beheerder op `competitions.edit`, een deelnemer op `competition.dashboard`.

## Een nieuwe speeldag heropent de beschikbaarheid voor iedereen

`availability_submitted_at` betekent "ingediend voor de huidige speeldagen". Elke plek die een speeldag aanmaakt, roept binnen dezelfde transactie `Competition::reopenAvailability()` aan. Die leegt de kolom voor alle deelnemers en laat de bestaande vinkjes staan (besluit Menno bij #74). Dat geldt ongeacht de competitiestatus: bouw er geen uitzondering voor concept of afgerond omheen, anders blijft er een gat bij actief → concept → speeldag erbij → actief. Zonder reset leest de planner "geen rij" op de nieuwe dag als "niet beschikbaar".

Valt hier (nog) NIET onder: een speeldag verzetten naar een andere datum (#81). Ook niet: een verouderd formulier dat na de reset wordt opgeslagen (#82).
