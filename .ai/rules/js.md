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

Een losse actie (een knop die alleen op zijn regel staat, ook een formulier-submit) krijgt daarnaast `w-full sm:w-auto`. Knoppen naast elkaar, zoals in de header of een knoppenrij, worden alleen hoger. Een knop met alleen een icoon (zoals de headerknoppen op mobiel) wordt met `size="touch"` vanzelf 44×44, via `min-w-11`. Een eigen interactief element dat geen `Button` is, krijgt `min-h-11 sm:min-h-0` (bv. `appearance-tabs`). Bestaat het alleen uit een icoon, dan krijgt het ook `min-w-11 sm:min-w-0` (bv. de kopieerknop in `two-factor-setup-modal`). Een element dat al groter is, laat je zoals het is. De sluitknop van `ui/dialog.tsx` heeft een onzichtbaar raakvlak van 44×44 (`before:-inset-3.5`).

Het beheer is desktop-only en deelt deze componenten. Vanaf `sm` is `touch` gelijk aan de default-maat (`h-9`). Een knop die eerst `size="sm"` had, groeit daar dus een paar pixels (bv. het prullenbakje bij een passkey). Dat is geaccepteerd.
