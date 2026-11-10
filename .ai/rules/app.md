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

Val: de 2FA-setup gaat voor. `EnsureAvailabilityIsSubmitted` laat een gebruiker met `User::requiresSecondFactorSetup()` door; zonder die guard ontstaat een redirect-lus tussen `two-factor.setup` en het formulier. Na een eerste inzending gaat iedereen terug naar de pagina die `EnsureAvailabilityIsSubmitted` onderschepte (sessiesleutel `availability.intended_url`). Die onthouden pagina geldt alleen voor Inertia-paginabezoeken (GET, geen prefetch) en is gekoppeld aan de competitie (`competition_id` + `url`); JSON-, download- en andere niet-Inertia-urls worden niet onthouden. Terugval is een beheerder naar `competitions.edit` en een deelnemer naar `competition.dashboard`.

## Een nieuwe speeldag vraagt iedereen opnieuw om beschikbaarheid

`availability_submitted_at` is het moment van de laatste inzending en wordt nooit geleegd. Een deelnemer moet (opnieuw) indienen als dat moment leeg is of ouder dan de nieuwste speeldag van de competitie (`MAX(match_days.created_at)`); een inzending in dezelfde seconde telt als actueel. Dat geldt ongeacht de competitiestatus (besluit Menno bij #74): bouw er geen uitzondering voor concept of afgerond omheen. Zonder die regel leest de planner "geen rij" op een nieuwe dag als "niet beschikbaar". De vinkjes blijven staan.

Gebruik altijd `Competition::constrainToAwaitingAvailability()` (SQL, op een relatie over `competition_user`) en `Competition::isCurrentSubmission()` (PHP). Lees de kolom nooit los op null: `wherePivotNull`/`whereNotNull` op `availability_submitted_at` mist wie vóór de nieuwste speeldag indiende. Het inzendmoment in PHP komt uit `User::availabilitySubmittedAtFor()` of `Competition::availabilitySubmittedAtFromPivot()`; alleen "ooit ingediend" (`User::hasEverSubmittedAvailabilityFor()`) kijkt bewust naar null. Een nieuwe speeldag slaat niets op bij de deelnemers, dus een speeldag verwijderen herstelt de stand vanzelf.

`is_new` en "nog niet ingevuld" volgen uit `MatchDay::isNewSince()` op het inzendmoment: een speeldag is nieuw als hij ná de laatste inzending is aangemaakt, ook na een lege inzending. "Nog niet ingevuld" is een niet-aangevinkte speeldag die nieuw is, of elke niet-aangevinkte speeldag van iemand die nooit indiende. Elk ander leeg vakje is "niet beschikbaar". Matrix, CSV en de alleen-lezenlijst volgen die regel; het totaal telt alle vinkjes.

Valt hier (nog) NIET onder: een speeldag verzetten naar een andere datum (#81). Ook niet: een verouderd formulier dat na een nieuwe speeldag wordt opgeslagen (#82).
