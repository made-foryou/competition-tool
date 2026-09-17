---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## Foutpagina's renderen via Inertia, niet via Laravel's vendor-pagina
403/404/419/500/503 renderen als Inertia-pagina `errors/error` in de console-stijl, geregistreerd met `Inertia::handleExceptionsUsing()` in `AppServiceProvider::configureErrorPages()`. Twee dingen die je niet mag weglaten of omdraaien:

- `->withSharedData()` is verplicht: `useTranslations` indexeert op `usePage().props.translations` en crasht zonder gedeelde props. `tests/Feature/ErrorPageTest.php` bewaakt dit met `->has('translations')`.
- 500/503 vallen in `local` en `testing` bewust terug op de standaard foutpagina, zodat de stacktrace zichtbaar blijft.

Val: op een url die op geen enkele route matcht draait de web-middlewaregroep niet, dus is er geen sessie en is `$request->user()` daar altijd null — ook voor wie ingelogd is. De uitweg op de foutpagina (`App\Support\ErrorPageExit`) wordt dan de startpagina. `actingAs()` maskeert dat in tests; test de rolafhankelijke uitweg daarom op een 404 van een route die wél matcht (bv. een onbekende slug op de competitie-prefix). Een `Route::fallback()` zou dit oplossen, maar laat `EnsureAvailabilityIsSubmitted` over elke typefout lopen en verandert een 404 in een redirect.
