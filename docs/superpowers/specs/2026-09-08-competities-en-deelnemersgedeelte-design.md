# Ontwerp: competitiebeheer & deelnemersgedeelte

Datum: 2026-09-08
Status: goedgekeurd ontwerp, klaar voor implementatieplan

## Doel

Twee uitbreidingen op de bestaande invite-only setup:

1. **Beheer**: competities aanmaken, wijzigen en verwijderen, inclusief het beheren van
   de deelnemerslijst per competitie.
2. **Deelnemersgedeelte**: een per competitie uniek gedeelte op een eigen url
   (`/{slug}`), met dezelfde loginflow als het beheer maar zonder verplichte 2FA.
   Dit gedeelte wordt tijdens competities vooral op mobiel gebruikt en is daarom
   mobile-first.

## Kernbeslissingen

- **Eén `users`-tabel voor iedereen** (beheerders én deelnemers), met een
  `role`-kolom. Reden: Laravel Fortify ondersteunt één guard/model; zo hergebruiken we
  de volledige bestaande loginflow (throttling, passkeys, 2FA-challenge,
  wachtwoord-reset) voor deelnemers.
- **Deelnemerschap = koppeling in `competition_user`**, los van de rol. Een admin kan
  dus ook deelnemer zijn aan een competitie; de rol regelt alleen beheer-toegang en
  2FA-verplichting.
- **Eén account, meerdere competities**: een gebruiker kan aan meerdere competities
  gekoppeld zijn.
- **2FA blijft verplicht voor admins, wordt optioneel voor deelnemers.**
- **Url per competitie is een pad met slug op root-niveau**: `/{slug}` en
  `/{slug}/login`, met een gereserveerde-slugs-lijst tegen routebotsingen.
- Zelfregistratie blijft uit (`Features::registration()` blijft uitgeschakeld).

## Datamodel

### `competitions` (nieuw)

| Kolom | Type | Opmerking |
| --- | --- | --- |
| `name` | string | |
| `slug` | string, uniek | Automatisch afgeleid van `name`, getoond in het formulier |
| `description` | text, nullable | |
| `location` | string, nullable | |
| `starts_at` | date | |
| `ends_at` | date, nullable | |
| `status` | string (enum `CompetitionStatus`) | `Draft` / `Active` / `Finished`, default `Draft` |

Model `Competition` met `HasFactory`, factory + seeder, relatie
`participants(): BelongsToMany<User>`.

### `users` (uitbreiding)

- Nieuwe kolom `role` (string, enum `UserRole`: `Admin` / `Participant`).
- Migratie zet bestaande gebruikers op `Admin` (huidige gebruikers zijn beheerders);
  default voor nieuwe rijen is `Participant`.
- Relatie `competitions(): BelongsToMany<Competition>`.
- Helper `isAdmin(): bool`.

### `competition_user` (nieuw, pivot)

`competition_id` + `user_id`, samen uniek, met timestamps. Dit is de deelnemerslijst.
Verwijderen van een competitie verwijdert de koppelingen (cascade), nooit de accounts.

### `invitations` (uitbreiding)

- Nullable `competition_id` (FK) en `role`-kolom.
- `php artisan app:invite {email}` blijft admin-uitnodigingen maken (`role = Admin`,
  geen competitie).
- Een deelnemer-uitnodiging hoort bij een competitie: bij acceptatie wordt de
  gebruiker aangemaakt met `role = Participant` en direct gekoppeld aan die
  competitie via `competition_user`.

### Enums

- `App\Enums\UserRole`: `Admin`, `Participant`.
- `App\Enums\CompetitionStatus`: `Draft`, `Active`, `Finished`.

## Routes & autorisatie

### Beheer

- Bestaande routes plus nieuwe resource-routes voor `/competitions` en routes voor
  deelnemersbeheer.
- Nieuwe middleware `EnsureUserIsAdmin` op de beheer-routes (`/dashboard` en alles
  onder `/competitions`): niet-admins krijgen een 403. De settings-routes blijven
  voor alle ingelogde gebruikers toegankelijk — deelnemers beheren daar hun
  wachtwoord en optionele 2FA.
- `EnsureTwoFactorIsConfigured` (globale web-middleware) dwingt 2FA voortaan alleen
  nog af voor gebruikers met rol `Admin`. Voor deelnemers is 2FA optioneel (in te
  stellen via de bestaande security-settings).

### Deelnemersgedeelte (`/{competition:slug}`)

- `GET /{slug}/login` — gastpagina; zet de intended-url op `/{slug}` zodat de
  bestaande Fortify-loginpipeline daarheen redirect na inloggen. Voor de
  status-check geldt hetzelfde als voor het dashboard: bij `Draft` geeft ook de
  loginpagina een 404 (gasten kunnen geen admin zijn).
- `GET /{slug}` — deelnemersdashboard, achter `auth` + nieuwe middleware
  `EnsureUserParticipatesInCompetition`.
- Toegangsregels voor `/{slug}`:
  - Gekoppelde gebruikers (deelnemers, inclusief admins die deelnemen) → toegang
    als deelnemer.
  - Niet-gekoppelde admins → ook toegang (controle), maar staan niet in de
    deelnemerslijst.
  - Niet-gekoppelde deelnemers → 403.
  - Status `Draft` → 404 voor iedereen behalve admins; `Active` en `Finished` zijn
    toegankelijk.
- Competitie-routes worden als **laatste** geregistreerd zodat bestaande routes
  altijd voorrang hebben op de slug-wildcard.

### Gereserveerde slugs

Validatieregel bij aanmaken/wijzigen van een competitie: de slug mag niet botsen met
bestaande top-level paden (o.a. `dashboard`, `login`, `logout`, `register`,
`settings`, `invitation`, `two-factor`, `two-factor-challenge`, `forgot-password`,
`reset-password`, `email`, `user`, `up`). De lijst staat op één centrale plek
(bijv. een constante op het Form Request of een config-array).

### Login-redirects

Custom `LoginResponse` (gebonden in `FortifyServiceProvider`):

1. Intended-url aanwezig → daarheen (dekt de `/{slug}/login`-flow).
2. Anders: admin → `/dashboard`.
3. Anders: deelnemer → `/{slug}` van de meest recente actieve competitie waaraan die
   is gekoppeld; zonder actieve competitie een eenvoudige Inertia-pagina met de
   melding dat er geen actieve competitie is (met uitlogknop).

## Beheer-UI (Inertia-pagina's onder `resources/js/pages/competitions/`)

- **Index** — tabel/lijst met naam, status, datums en aantal deelnemers; knop
  "nieuwe competitie".
- **Create/Edit** — formulier: naam (slug automatisch afgeleid en getoond),
  omschrijving, locatie, start-/einddatum, status. Validatie via Form Requests
  (`StoreCompetitionRequest` / `UpdateCompetitionRequest`), inclusief
  gereserveerde-slugs-check.
- **Verwijderen** — met bevestigingsdialoog.
- **Deelnemerslijst** op de competitie-detail-/editpagina:
  - Gekoppelde deelnemers tonen (naam, e-mail, uitnodiging-status waar relevant).
  - Ontkoppelen (met bevestiging).
  - Toevoegen via e-mailadres:
    - Bestaand account (ook admin) → direct koppelen, rol blijft ongewijzigd.
    - Onbekend e-mailadres → beheerder kiest: uitnodigingsmail sturen (bestaande
      `InvitationNotification`-flow, nu met competitie-context) óf account direct
      aanmaken met naam + wachtwoord.
- Wayfinder voor alle route-verwijzingen; UI-copy via `t()` met Engelse keys en
  NL-vertalingen in `lang/nl.json`.

## Deelnemersgedeelte (mobile-first)

Het deelnemersgedeelte wordt **mobile-first** ontworpen en gebouwd: layouts,
typografie en tap-targets primair voor smalle schermen (Tailwind zonder
breakpoint-prefix = mobiel), met `sm:`/`md:`-uitbreidingen voor grotere schermen.
Dit geldt voor alle pagina's in dit gedeelte, ook toekomstige (wedstrijden,
standen, …).

- **`/{slug}/login`** — Made console-stijl (altijd donker), opgebouwd met
  `@/components/console/*` conform `.ai/rules/auth.md`; toont de competitienaam,
  met wachtwoord-vergeten-link en passkey-ondersteuning.
- **`/{slug}` (dashboard)** — eigen lichte deelnemerslayout (los van de
  beheer-layout, wel met dark-mode-ondersteuning zoals de rest van de app):
  competitiegegevens (naam, status, datums, locatie, omschrijving) en de
  deelnemerslijst. Echte competitie-inhoud (wedstrijden, standen, scheidsrechters)
  volgt in latere stappen.
- Gedeelde deelnemerslayout-component zodat volgende pagina's dezelfde navigatie en
  competitie-context krijgen.

## Testen (Pest, feature tests)

- Competitie-CRUD: admin kan aanmaken/wijzigen/verwijderen; deelnemer krijgt 403;
  gast wordt geredirect.
- Slug: automatische generatie, uniekheid, gereserveerde slugs geweigerd.
- Deelnemersbeheer: koppelen van bestaand account (incl. admin), ontkoppelen,
  uitnodigen van nieuw e-mailadres, direct aanmaken; acceptatie van een
  deelnemer-uitnodiging maakt account met rol `Participant` + koppeling.
- Toegang `/{slug}`: gekoppelde deelnemer ✓, gekoppelde admin ✓, niet-gekoppelde
  admin ✓, niet-gekoppelde deelnemer 403, `Draft` → 404 (behalve admin), gast →
  redirect naar `/{slug}/login`.
- Login: via `/{slug}/login` kom je na inloggen op `/{slug}` uit; centrale login
  stuurt admin naar dashboard en deelnemer naar diens actieve competitie.
- 2FA: admin zonder 2FA wordt naar setup gedwongen; deelnemer zonder 2FA niet.
- Bestaande auth-/invitationtests blijven groen (regressie op de `role`-kolom en
  invitation-uitbreiding).

## Buiten scope (latere stappen)

- Wedstrijden, speelschema's, tussenstanden, scheidsrechters en locaties per
  wedstrijd in het deelnemersgedeelte.
- Profiel-/instellingenpagina's specifiek voor deelnemers (deelnemers kunnen de
  bestaande settings gebruiken).
- Verdere uitbreiding van het beheer (genoemd als vervolg, niet in deze stap).
