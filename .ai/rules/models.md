---
paths:
    - 'app/Models/**'
---

# Models

## Context-foreign keys staan niet in #[Fillable]

Eén regel voor alle modellen: een foreign key die uit de route of uit de ingelogde gebruiker volgt, is niet mass-assignable. Dus geen `user_id` op MatchDayAvailability, geen `competition_id` op MatchDay, geen `match_day_id` op MatchDayField, geen `invited_by`/`competition_id` op Invitation.

Leg die koppeling bij voorkeur via de relatie: `$competition->matchDays()->create(...)`, `$user->matchDayAvailabilities()->create(...)`.

`forceFill()` is het alternatief waar geen relatie bruikbaar is. In `SendInvitation` is dat zo omdat competitie én uitnodiger optioneel zijn — een uitnodiging voor een beheerder hangt aan geen enkele competitie, dus `$competition->invitations()->create(...)` zou een tweede tak vergen voor het `null`-geval. Dat is de uitzondering, niet het patroon: `forceFill()` omzeilt álle guarding, dus geef het uitsluitend server-side bepaalde waardes en nooit request-data.

Een FK die de gebruiker echt als formulierwaarde kiest (`match_day_id` op MatchDayAvailability) mag wel fillable zijn. `tests/Feature/MassAssignmentTest.php` bewaakt de regel; breid de dataset uit bij een nieuw model.
