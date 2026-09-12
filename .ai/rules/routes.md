---
paths:
    - 'routes/**'
---

# Routes

## Regenerate Wayfinder met --with-form

Draai altijd `php artisan wayfinder:generate --with-form` na een routewijziging. De vite-plugin staat op `formVariants: true`, dus zonder de vlag verdwijnen alle `.form`-varianten uit resources/js/actions en routes en breekt `npm run types:check` op ~20 bestaande bestanden.
