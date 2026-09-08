---
paths:
    - 'resources/js/**'
---

# Js

## UI-teksten via useTranslations, Engelse bronstrings als key

Alle UI-copy gaat door de `t()` van `@/hooks/use-translations` (gedeeld via HandleInertiaRequests uit `lang/{locale}.json`; APP_LOCALE=nl). Keys zijn de Engelse bronstrings; de NL-vertaling staat in `lang/nl.json`. Nieuwe teksten dus altijd als Engelse key toevoegen aan `lang/nl.json` — nooit hardcoded Nederlands in componenten.
