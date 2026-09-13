---
paths:
    - 'database/**'
---

# Database

## Nooit migrate:fresh of andere database-wissende commando's draaien

De lokale dev-database bevat handmatig opgebouwde testdata van Menno die moet blijven bestaan. Draai daarom nooit `php artisan migrate:fresh`, `migrate:refresh`, `db:wipe` of andere destructieve DB-commando's — ook niet als sanity-check. Nieuwe migraties test je met een gewone `php artisan migrate`; de volledige migratieketen wordt al gedekt door de testsuite (RefreshDatabase draait op de aparte testdatabase, die is wél vrij herbruikbaar).
