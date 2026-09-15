---
paths:
    - 'app/Models/**'
---

# Models

## Context-foreign keys staan niet in #[Fillable]

Eén regel voor alle modellen: een foreign key die uit de route of uit de ingelogde gebruiker volgt, is niet mass-assignable. Dus geen `user_id` op MatchDayAvailability, geen `competition_id` op MatchDay, geen `match_day_id` op MatchDayField, geen `invited_by`/`competition_id` op Invitation.

Leg die koppeling via de relatie (`$competition->matchDays()->create(...)`, `$user->matchDayAvailabilities()->create(...)`) of, waar geen relatie voorhanden is, met `forceFill()` — zoals `SendInvitation` doet voor token, invited_by en competition_id. `forceFill()` mag alleen server-side bepaalde waardes krijgen, nooit request-data.

Een FK die de gebruiker echt als formulierwaarde kiest (`match_day_id` op MatchDayAvailability) mag wel fillable zijn. `tests/Feature/MassAssignmentTest.php` bewaakt de regel; breid de dataset uit bij een nieuw model.
