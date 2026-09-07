---
paths:
  - 'resources/js/pages/auth/**'
---

# Auth

## Auth-schermen gebruiken de Made console-componenten
Auth-pagina's renderen in de altijd-donkere Made console-stijl: layoutprop `Page.layout = { label: '…' }` zet het mono-headerlabel van de kaart, opbouw via `@/components/console/*` (ConsoleHeading/Input/Button/Error, ConsolePasskeyButton). Design-tokens zijn genamespaced in `resources/css/app.css` (`--color-console-*`, `--color-copper`); entrance-animaties via `.made-anim` + `--made-delay` (reduced-motion wordt centraal uitgezet). Volg het Claude Design-project "made-design-system" voor nieuwe schermen.
