---
paths:
    - '**'
---

# General

## GitHub Issues is de tasktracker van dit project

Features, taken en reviewbevindingen worden bijgehouden als issues in `made-foryou/competition-tool` op GitHub — niet in losse plannings- of TODO-bestanden.

Gebruik de `gh` CLI om werk op te halen en vast te leggen: `gh issue list`, `gh issue view <nr>`, `gh issue create`. Labels die in gebruik zijn: `enhancement` (feature) en `review-finding` (bevinding uit een review). Issues worden gegroepeerd in milestones (bv. "First release - testing round").

Branchnaam volgt het issue: `<nummer>-<slug>`, bv. `3-wedstrijden-genereren-per-competitie-type-theo-schilthuizen-bokaal`.

Bevindingen uit reviews die niet direct gefixt worden, horen als apart issue met label `review-finding` in GitHub — niet als TODO-comment in de code.
