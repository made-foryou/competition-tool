---
paths:
    - '.ai/rules/**'
---

# Rules

## Na record-rule altijd vp check --fix draaien op .ai/rules

`record-rule` schrijft `.ai/rules/*.md` (en de tabel in `index.md`) zonder de opmaak van de projectformatter. `vp check` in CI (`composer ci:check`) controleert ook die markdown en faalt dan vóór de tests (gebeurd bij PR #86). Draai daarom na elke `record-rule` `npx vp check --fix .ai/rules` en commit het resultaat mee.
