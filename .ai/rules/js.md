---
paths:
    - 'resources/js/**'
---

# Js

## UI-teksten via useTranslations, Engelse bronstrings als key

Alle UI-copy gaat door de `t()` van `@/hooks/use-translations` (gedeeld via HandleInertiaRequests uit `lang/{locale}.json`; APP_LOCALE=nl). Keys zijn de Engelse bronstrings; de NL-vertaling staat in `lang/nl.json`. Nieuwe teksten dus altijd als Engelse key toevoegen aan `lang/nl.json` — nooit hardcoded Nederlands in componenten.

## Deferred props met een dialoog erin: useRetainedProp in plaats van Deferred

Een response met validatiefouten (en de eerste response na een redirect) bevat geen `Inertia::defer`-props. Met `<Deferred>` valt de tab dan terug op het skelet en verdwijnt een open dialoog met haar veldfouten uit de boom. Op `pages/competitions/edit.tsx` lopen `matches` en `schedule` daarom via `useRetainedProp` (`@/hooks/use-retained-prop`): het skelet alleen bij de allereerste load, daarna de laatst geladen waarde tot de verse binnen is. Gebruik hetzelfde patroon voor elke deferred prop waarin een formulier of dialoog leeft.

## Deelnemersgedeelte: elke knop size="touch", losse acties op mobiel volle breedte

Besluit Menno bij #85: het deelnemersgedeelte is mobile-first, dus elk tap-target is op mobiel minstens 44×44px. Elke `Button` die een deelnemer ziet gebruikt `size="touch"` (44×44 onder `sm`, `h-9` vanaf `sm`). Dat geldt voor `pages/participant/**`, `layouts/participant/**` en de gedeelde componenten op de deelnemersinstellingen (2FA, passkeys, hun dialogen). Gebruik daar geen `size="sm"` of de default-maat.

Een losse actie (een knop die alleen op zijn regel staat, ook een formulier-submit) krijgt daarnaast `w-full sm:w-auto`. Knoppen naast elkaar, zoals in de header of een knoppenrij, worden alleen hoger. Een eigen interactief element dat geen `Button` is (bv. `appearance-tabs`) krijgt `min-h-11 sm:min-h-0`, tenzij het al groter is.

Het beheer is desktop-only. Daar verandert de maat pas onder `sm`, dus het delen van deze componenten is geen probleem.
