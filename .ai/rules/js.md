---
paths:
    - 'resources/js/**'
---

# Js

## UI-teksten via useTranslations, Engelse bronstrings als key

Alle UI-copy gaat door de `t()` van `@/hooks/use-translations` (gedeeld via HandleInertiaRequests uit `lang/{locale}.json`; APP_LOCALE=nl). Keys zijn de Engelse bronstrings; de NL-vertaling staat in `lang/nl.json`. Nieuwe teksten dus altijd als Engelse key toevoegen aan `lang/nl.json` — nooit hardcoded Nederlands in componenten.

## Deferred props met een dialoog erin: useRetainedProp in plaats van Deferred

Een response met validatiefouten (en de eerste response na een redirect) bevat geen `Inertia::defer`-props. Met `<Deferred>` valt de tab dan terug op het skelet en verdwijnt een open dialoog met haar veldfouten uit de boom. Op `pages/competitions/edit.tsx` lopen `matches` en `schedule` daarom via `useRetainedProp` (`@/hooks/use-retained-prop`): het skelet alleen bij de allereerste load, daarna de laatst geladen waarde tot de verse binnen is. Gebruik hetzelfde patroon voor elke deferred prop waarin een formulier of dialoog leeft.
