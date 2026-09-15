---
paths:
    - 'app/**'
---

# App

## Aanmelden voor een competitie kan alleen als die actief is

Eén lijn voor alle aanmeldroutes: `CompetitionStatus::allowsSignUp()` (alleen `Active`) bepaalt of iemand zich voor een competitie kan aanmelden — via de publieke inschrijfpagina én via een uitnodiging, met of zonder bestaand account. Bouw hier geen uitzondering per route omheen; dat verschil bestond eerder wel (uitnodigingen mochten op `Draft`) en is bewust weggehaald.

Gevolg: een uitnodiging die tijdens de conceptfase is verstuurd, werkt pas zodra de competitie actief staat. De genodigde krijgt zolang de `upcoming`-staat op de uitnodigingspagina.

Valt hier NIET onder: een beheerder die zelf een deelnemer koppelt (`CompetitionParticipantController`). Die bouwt de deelnemerslijst op, ook tijdens de conceptfase, en heeft daarom geen statusguard.
