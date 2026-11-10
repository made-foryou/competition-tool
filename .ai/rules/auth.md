---
paths:
    - 'resources/js/pages/auth/**'
---

# Auth

## Auth-schermen gebruiken de Made console-componenten

Auth-pagina's renderen in de altijd-donkere Made console-stijl: layoutprop `Page.layout = { label: '…' }` zet het mono-headerlabel van de kaart, opbouw via `@/components/console/*` (ConsoleHeading/Input/Button/Error, ConsolePasskeyButton). Design-tokens zijn genamespaced in `resources/css/app.css` (`--color-console-*`, `--color-copper`); entrance-animaties via `.made-anim` + `--made-delay` (reduced-motion wordt centraal uitgezet). Volg het Claude Design-project "made-design-system" voor nieuwe schermen.

## 2FA-setup: na bevestigen geen Inertia-visit of nieuw request

Op `auth/two-factor-setup.tsx` gaat het bevestigen van TOTP via `useHttp` (JSON), niet via Inertia `<Form>`. Fortify antwoordt op een Inertia-request met `back()`, en `TwoFactorSetupController` stuurt een gebruiker die 2FA heeft door naar `dashboard`. De herstelcodes-stap zou dan nooit verschijnen (#75).

De herstelcodes worden al opgehaald bij het openen van de stap `totp-confirm`. Na de bevestiging geldt `requiresSecondFactorSetup()` niet meer en doet `EnsureAvailabilityIsSubmitted` mee, waardoor een beheerder-deelnemer bij elk nieuw request wordt omgeleid. Een stap ná de bevestiging werkt dus alleen met data die al in state staat.
