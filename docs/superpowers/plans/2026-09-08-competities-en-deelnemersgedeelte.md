# Competitiebeheer & Deelnemersgedeelte — Implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Competities kunnen in het beheer worden aangemaakt/gewijzigd/verwijderd (incl. deelnemerslijst), en elke competitie krijgt een eigen mobile-first deelnemersgedeelte op `/{slug}` met de bestaande Fortify-loginflow.

**Architecture:** Eén `users`-tabel met `role`-kolom (Admin/Participant); deelnemerschap = koppeling in de `competition_user`-pivot, los van de rol. Beheer wordt afgeschermd met een `EnsureUserIsAdmin`-middleware, het deelnemersgedeelte met een status-check (`EnsureCompetitionIsVisible`) plus een membership-check (`EnsureUserParticipatesInCompetition`). De Fortify-pipeline blijft ongewijzigd; een custom `LoginResponse` regelt rolafhankelijke redirects, en 2FA wordt alleen nog afgedwongen voor admins.

**Tech Stack:** PHP 8.5 / Laravel 13, Laravel Fortify, Inertia v3 + React 19, Wayfinder, Tailwind CSS v4, Pest 5, Larastan level 7.

**Spec:** `docs/superpowers/specs/2026-09-08-competities-en-deelnemersgedeelte-design.md`

## Global Constraints

- Werk op branch `feature/competities-en-deelnemersgedeelte`; nooit pushen zonder toestemming.
- Na elke PHP-wijziging vóór commit: `vendor/bin/pint --dirty --format agent`.
- PHPStan level 7 moet slagen: `composer types:check` (draai minimaal in Task 12; bij twijfel over generics eerder).
- Tests draaien met `php artisan test --compact <pad>`; site draait via Herd (`https://competition-tool.test`) — nooit zelf een server starten.
- Alle UI-copy via `t()` uit `@/hooks/use-translations`; **Engelse bronstring als key**, NL-vertaling toevoegen aan `lang/nl.json`. Nooit hardcoded Nederlands in componenten.
- `resources/js/actions/`, `resources/js/routes/` en `resources/js/wayfinder/` zijn gegenereerd — nooit handmatig bewerken; na route-wijzigingen `php artisan wayfinder:generate` draaien.
- Pagina's onder `resources/js/pages/auth/**` gebruiken de Made console-componenten (`@/components/console/*`), layoutprop `Page.layout = { label: '…' }`, animaties via `.made-anim` + `--made-delay` (zie `.ai/rules/auth.md`).
- Het deelnemersgedeelte (`resources/js/pages/participant/**`) is **mobile-first**: basisklassen voor mobiel, `sm:`/`md:` voor groter.
- Models gebruiken de attribute-stijl `#[Fillable([...])]` en een `casts()`-methode (zie `app/Models/User.php`); PHPDoc `@property`-blokken voor alle kolommen (Larastan level 7).
- Geen nieuwe composer-/npm-dependencies.
- Bestaande tests mogen niet stukgaan; verwijder geen tests.

---

### Task 1: UserRole-enum + `role`-kolom op users

**Files:**

- Create: `app/Enums/UserRole.php`
- Create: migratie via `php artisan make:migration add_role_to_users_table --no-interaction`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/UserRoleTest.php`

**Interfaces:**

- Consumes: bestaande `User`-model/factory.
- Produces: `App\Enums\UserRole` (`Admin`/`Participant`, string-backed `'admin'`/`'participant'`), `User::$role` (cast naar `UserRole`), `User::isAdmin(): bool`, factory-default `Admin`, factory-state `User::factory()->participant()`.

- [ ] **Step 1: Schrijf de failing test**

Maak `tests/Feature/UserRoleTest.php` (via `php artisan make:test --pest UserRoleTest --no-interaction`, inhoud vervangen):

```php
<?php

use App\Enums\UserRole;
use App\Models\User;

test('factory users are admins by default', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue();
});

test('the participant factory state creates participants', function () {
    $user = User::factory()->participant()->create();

    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->isAdmin())->toBeFalse();
});

test('users created without an explicit role are participants', function () {
    $user = User::create([
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    expect($user->refresh()->role)->toBe(UserRole::Participant);
});
```

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/UserRoleTest.php`
Expected: FAIL (kolom `role` bestaat niet / enum niet gevonden).

- [ ] **Step 3: Implementeer enum, migratie en model**

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Participant = 'participant';
}
```

Migratie (`database/migrations/*_add_role_to_users_table.php`):

```php
<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::Participant->value)->after('email');
        });

        // Bestaande gebruikers zijn beheerders.
        DB::table('users')->update(['role' => UserRole::Admin->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

`app/Models/User.php` — voeg toe aan het PHPDoc-blok: `@property UserRole $role` (import `App\Enums\UserRole`). `role` NIET aan `#[Fillable]` toevoegen (rol wordt bewust via `forceFill`/default gezet). Voeg toe aan de class:

```php
public function isAdmin(): bool
{
    return $this->role === UserRole::Admin;
}
```

En in `casts()`:

```php
'role' => UserRole::class,
```

`database/factories/UserFactory.php` — voeg in `definition()` toe: `'role' => UserRole::Admin,` (import `App\Enums\UserRole`) en de state:

```php
/**
 * Indicate that the user is a competition participant.
 */
public function participant(): static
{
    return $this->state(fn (array $attributes) => [
        'role' => UserRole::Participant,
    ]);
}
```

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/UserRoleTest.php`
Expected: PASS (3 tests).

Run ook de bestaande auth-tests als regressiecheck: `php artisan test --compact tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: voeg role-kolom en UserRole-enum toe aan users"
```

---

### Task 2: Competition-model, status-enum, pivot en factory

**Files:**

- Create: `app/Enums/CompetitionStatus.php`
- Create: `app/Models/Competition.php`, `database/factories/CompetitionFactory.php`, `database/seeders/CompetitionSeeder.php` (via `php artisan make:model Competition --migration --factory --seed --no-interaction`)
- Create: migratie via `php artisan make:migration create_competition_user_table --no-interaction`
- Modify: `app/Models/User.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/CompetitionModelTest.php`

**Interfaces:**

- Consumes: `App\Models\User`, `App\Enums\UserRole` (Task 1).
- Produces: `App\Enums\CompetitionStatus` (`Draft`/`Active`/`Finished`, string-backed `'draft'`/`'active'`/`'finished'`), `Competition` met kolommen `name, slug, description, location, starts_at, ends_at, status`, relatie `Competition::participants(): BelongsToMany<User>`, `User::competitions(): BelongsToMany<Competition>`, `Competition::RESERVED_SLUGS` (`list<string>`), factory-default status `Active` met states `draft()` en `finished()`.

- [ ] **Step 1: Schrijf de failing test**

`tests/Feature/CompetitionModelTest.php`:

```php
<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;

test('a competition casts its fields and has participants', function () {
    $competition = Competition::factory()->create(['name' => 'Voorjaarstoernooi 2026']);
    $user = User::factory()->participant()->create();

    $competition->participants()->attach($user);

    expect($competition->status)->toBe(CompetitionStatus::Active)
        ->and($competition->starts_at->toDateString())->toBeString()
        ->and($competition->participants()->count())->toBe(1)
        ->and($user->competitions()->count())->toBe(1);
});

test('factory states set the status', function () {
    expect(Competition::factory()->draft()->create()->status)->toBe(CompetitionStatus::Draft)
        ->and(Competition::factory()->finished()->create()->status)->toBe(CompetitionStatus::Finished);
});

test('deleting a competition removes the pivot rows but keeps the users', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $competition->delete();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->competitions()->count())->toBe(0);
});
```

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/CompetitionModelTest.php`
Expected: FAIL (model/tabel bestaat niet).

- [ ] **Step 3: Implementeer enum, migraties, model, factory, seeder**

`app/Enums/CompetitionStatus.php`:

```php
<?php

namespace App\Enums;

enum CompetitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Finished = 'finished';
}
```

Migratie `*_create_competitions_table.php`:

```php
<?php

use App\Enums\CompetitionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('status')->default(CompetitionStatus::Draft->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
```

Migratie `*_create_competition_user_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['competition_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_user');
    }
};
```

`app/Models/Competition.php`:

```php
<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property CompetitionStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'location', 'starts_at', 'ends_at', 'status'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    /**
     * Slugs die botsen met bestaande top-level routes en dus nooit als
     * competitie-slug gebruikt mogen worden.
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = [
        'competitions',
        'dashboard',
        'email',
        'forgot-password',
        'invitation',
        'login',
        'logout',
        'no-competition',
        'register',
        'reset-password',
        'settings',
        'two-factor',
        'two-factor-challenge',
        'up',
        'user',
        'welcome',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => CompetitionStatus::class,
        ];
    }
}
```

`database/factories/CompetitionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'starts_at' => now()->addWeek()->toDateString(),
            'ends_at' => now()->addWeek()->addDay()->toDateString(),
            'status' => CompetitionStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CompetitionStatus::Draft]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CompetitionStatus::Finished]);
    }
}
```

`database/seeders/CompetitionSeeder.php` (gegenereerde class invullen):

```php
<?php

namespace Database\Seeders;

use App\Models\Competition;
use Illuminate\Database\Seeder;

class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        Competition::factory()->count(2)->create();
        Competition::factory()->draft()->create();
    }
}
```

Registreer in `database/seeders/DatabaseSeeder.php` binnen `run()`: `$this->call(CompetitionSeeder::class);`

`app/Models/User.php` — voeg de relatie toe:

```php
/**
 * @return BelongsToMany<Competition, $this>
 */
public function competitions(): BelongsToMany
{
    return $this->belongsToMany(Competition::class)->withTimestamps();
}
```

(import `Illuminate\Database\Eloquent\Relations\BelongsToMany`).

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/CompetitionModelTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: voeg Competition-model met status-enum en deelnemers-pivot toe"
```

---

### Task 3: EnsureUserIsAdmin-middleware op het beheer

**Files:**

- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardTest.php` (uitbreiden)

**Interfaces:**

- Consumes: `User::isAdmin()` (Task 1).
- Produces: `App\Http\Middleware\EnsureUserIsAdmin` (403 voor niet-admins); het dashboard zit achter `['auth', 'verified', EnsureUserIsAdmin::class]`. Latere taken hangen `/competitions`-routes in dezelfde groep.

- [ ] **Step 1: Schrijf de failing tests**

Voeg toe aan `tests/Feature/DashboardTest.php`:

```php
test('participants cannot access the dashboard', function () {
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
```

(Zorg dat `use App\Models\User;` bovenaan staat; bestaande tests laten staan.)

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/DashboardTest.php`
Expected: FAIL — participant krijgt nu een redirect naar two-factor-setup of 200, geen 403.

- [ ] **Step 3: Implementeer middleware + route-groep**

`app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schermt beheer-routes af: alleen gebruikers met de rol Admin mogen erin.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $next($request);
    }
}
```

In `routes/web.php` de dashboard-groep vervangen door:

```php
Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});
```

(import `App\Http\Middleware\EnsureUserIsAdmin`). De settings-routes (`routes/settings.php`) blijven ongewijzigd — die moeten voor deelnemers toegankelijk blijven.

Let op: de test uit Step 1 faalt hierna mogelijk nóg omdat de globale `EnsureTwoFactorIsConfigured` de participant eerst naar two-factor-setup redirect. Dat wordt in Task 4 opgelost; draai de test hier alleen om te zien dat de middleware bestaat. Als de test op de 2FA-redirect faalt: geef de participant in de test tijdelijk NIET aanpassen — ga door naar Task 4 en committeer Task 3 + 4 samen als de test pas daarna groen is.

- [ ] **Step 4: Run de test**

Run: `php artisan test --compact tests/Feature/DashboardTest.php`
Expected: PASS zodra Task 4 ook is uitgevoerd (zie noot hierboven); anders FAIL op de 2FA-redirect — dan direct door naar Task 4.

- [ ] **Step 5: Pint + commit (eventueel samen met Task 4)**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: scherm beheer-routes af met EnsureUserIsAdmin"
```

---

### Task 4: 2FA alleen nog verplicht voor admins

**Files:**

- Modify: `app/Http/Middleware/EnsureTwoFactorIsConfigured.php`
- Test: `tests/Feature/Auth/TwoFactorEnforcementTest.php` (uitbreiden)

**Interfaces:**

- Consumes: `User::isAdmin()` (Task 1).
- Produces: participants worden nooit naar `two-factor.setup` geforceerd; gedrag voor admins ongewijzigd.

- [ ] **Step 1: Schrijf de failing tests**

Voeg toe aan `tests/Feature/Auth/TwoFactorEnforcementTest.php`:

```php
test('a participant without a second factor is not forced into two factor setup', function () {
    $user = User::factory()->participant()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('an admin without a second factor is still forced into two factor setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('two-factor.setup'));
});
```

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/Auth/TwoFactorEnforcementTest.php`
Expected: FAIL — de participant wordt nu ook naar `two-factor.setup` geredirect.

- [ ] **Step 3: Implementeer de rolcheck**

In `app/Http/Middleware/EnsureTwoFactorIsConfigured.php`, direct na de bestaande `if (! $user instanceof User)`-check:

```php
if (! $user->isAdmin()) {
    return $next($request);
}
```

Werk het class-level PHPDoc bij: de verplichting geldt alleen voor beheerders; voor deelnemers is 2FA optioneel.

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/Auth/TwoFactorEnforcementTest.php tests/Feature/DashboardTest.php`
Expected: PASS (inclusief de participant-403-test uit Task 3).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: dwing 2FA alleen af voor admins"
```

---

### Task 5: Competitie-CRUD backend (controller, requests, routes)

**Files:**

- Create: `app/Http/Requests/Competitions/StoreCompetitionRequest.php`, `app/Http/Requests/Competitions/UpdateCompetitionRequest.php`
- Create: `app/Http/Controllers/CompetitionController.php` (via `php artisan make:controller CompetitionController --no-interaction`)
- Modify: `routes/web.php`
- Test: `tests/Feature/CompetitionManagementTest.php`

**Interfaces:**

- Consumes: `Competition`, `Competition::RESERVED_SLUGS`, `CompetitionStatus` (Task 2), admin-routegroep (Task 3).
- Produces: routes `competitions.index/create/store/edit/update/destroy` (resource zonder `show`, binding op id). `store`/`update` leiden de slug server-side af uit `name` (`Str::slug`); valideren uniek + niet-gereserveerd (foutkey `slug`). `edit` levert prop `competition` (`{id, name, slug, description, location, starts_at, ends_at, status}`); `index` levert `competitions` (zelfde velden + `participants_count`).

- [ ] **Step 1: Schrijf de failing tests**

`tests/Feature/CompetitionManagementTest.php`:

```php
<?php

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

test('admins can view the competitions index', function () {
    actingAsAdmin();
    Competition::factory()->count(2)->create();

    $this->get(route('competitions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('competitions/index')
            ->has('competitions', 2),
        );
});

test('participants cannot access competition management', function () {
    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competitions.index'))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $this->get(route('competitions.index'))->assertRedirect(route('login'));
});

test('admins can create a competition with an auto-generated slug', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Voorjaarstoernooi 2026',
        'description' => 'Het jaarlijkse toernooi.',
        'location' => 'Sporthal Noord',
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-02',
        'status' => CompetitionStatus::Draft->value,
    ])->assertRedirect();

    $competition = Competition::query()->firstOrFail();
    expect($competition->slug)->toBe('voorjaarstoernooi-2026')
        ->and($competition->status)->toBe(CompetitionStatus::Draft);
});

test('a competition name resulting in a reserved slug is rejected', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Dashboard',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('slug');
});

test('a duplicate slug is rejected', function () {
    actingAsAdmin();
    Competition::factory()->create(['slug' => 'voorjaarstoernooi-2026']);

    $this->post(route('competitions.store'), [
        'name' => 'Voorjaarstoernooi 2026',
        'starts_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('slug');
});

test('admins can update a competition and keep its own slug', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create(['name' => 'Oud', 'slug' => 'oud']);

    $this->put(route('competitions.update', $competition), [
        'name' => 'Oud',
        'description' => 'Bijgewerkt.',
        'location' => null,
        'starts_at' => '2026-11-01',
        'ends_at' => null,
        'status' => CompetitionStatus::Active->value,
    ])->assertRedirect(route('competitions.edit', $competition));

    expect($competition->refresh()->description)->toBe('Bijgewerkt.')
        ->and($competition->status)->toBe(CompetitionStatus::Active);
});

test('the end date may not be before the start date', function () {
    actingAsAdmin();

    $this->post(route('competitions.store'), [
        'name' => 'Toernooi',
        'starts_at' => '2026-10-02',
        'ends_at' => '2026-10-01',
        'status' => CompetitionStatus::Draft->value,
    ])->assertSessionHasErrors('ends_at');
});

test('admins can delete a competition', function () {
    actingAsAdmin();
    $competition = Competition::factory()->create();

    $this->delete(route('competitions.destroy', $competition))
        ->assertRedirect(route('competitions.index'));

    expect(Competition::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/CompetitionManagementTest.php`
Expected: FAIL (routes bestaan niet).

- [ ] **Step 3: Implementeer requests, controller en routes**

`app/Http/Requests/Competitions/StoreCompetitionRequest.php`:

```php
<?php

namespace App\Http\Requests\Competitions;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCompetitionRequest extends FormRequest
{
    /**
     * De slug wordt altijd server-side afgeleid van de naam.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::notIn(Competition::RESERVED_SLUGS), Rule::unique('competitions', 'slug')],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::enum(CompetitionStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.not_in' => __('This name results in a reserved URL. Choose a different name.'),
            'slug.unique' => __('A competition with this URL already exists. Choose a different name.'),
        ];
    }
}
```

`app/Http/Requests/Competitions/UpdateCompetitionRequest.php` — identiek, behalve de unique-regel:

```php
<?php

namespace App\Http\Requests\Competitions;

use App\Models\Competition;
use Illuminate\Validation\Rule;

class UpdateCompetitionRequest extends StoreCompetitionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = ['required', 'string', 'max:255', Rule::notIn(Competition::RESERVED_SLUGS), Rule::unique('competitions', 'slug')->ignore($this->route('competition'))];

        return $rules;
    }
}
```

`app/Http/Controllers/CompetitionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Competitions\StoreCompetitionRequest;
use App\Http\Requests\Competitions\UpdateCompetitionRequest;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('competitions/index', [
            'competitions' => Competition::query()
                ->withCount('participants')
                ->orderByDesc('starts_at')
                ->get()
                ->map(fn (Competition $competition): array => [
                    ...$this->competitionProps($competition),
                    'participants_count' => $competition->participants_count,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('competitions/create');
    }

    public function store(StoreCompetitionRequest $request): RedirectResponse
    {
        $competition = Competition::create($request->validated());

        return redirect()->route('competitions.edit', $competition);
    }

    public function edit(Competition $competition): Response
    {
        return Inertia::render('competitions/edit', [
            'competition' => $this->competitionProps($competition),
        ]);
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition): RedirectResponse
    {
        $competition->update($request->validated());

        return redirect()->route('competitions.edit', $competition);
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();

        return redirect()->route('competitions.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function competitionProps(Competition $competition): array
    {
        return [
            'id' => $competition->id,
            'name' => $competition->name,
            'slug' => $competition->slug,
            'description' => $competition->description,
            'location' => $competition->location,
            'starts_at' => $competition->starts_at->toDateString(),
            'ends_at' => $competition->ends_at?->toDateString(),
            'status' => $competition->status->value,
        ];
    }
}
```

In `routes/web.php`, binnen de admin-groep uit Task 3:

```php
Route::resource('competitions', CompetitionController::class)->except(['show']);
```

(import `App\Http\Controllers\CompetitionController`).

Voeg de validatieteksten toe aan `lang/nl.json`:

```json
"This name results in a reserved URL. Choose a different name.": "Deze naam levert een gereserveerde URL op. Kies een andere naam.",
"A competition with this URL already exists. Choose a different name.": "Er bestaat al een competitie met deze URL. Kies een andere naam."
```

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/CompetitionManagementTest.php`
Expected: PASS (10 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: competitie-CRUD backend met slug-validatie"
```

---

### Task 6: Beheer-UI voor competities (React)

**Files:**

- Create: `resources/js/pages/competitions/index.tsx`, `resources/js/pages/competitions/create.tsx`, `resources/js/pages/competitions/edit.tsx`, `resources/js/components/competitions/competition-form.tsx`
- Modify: `lang/nl.json`
- Verify: `php artisan wayfinder:generate`, `npm run check`, `npm run types:check`

**Interfaces:**

- Consumes: routes/props uit Task 5; Wayfinder-functies uit `@/routes/competitions` (`index`, `create`, `store`, `edit`, `update`, `destroy`); ui-componenten uit `@/components/ui/*`; `t()` uit `@/hooks/use-translations`.
- Produces: type `CompetitionProps` (geëxporteerd uit `competition-form.tsx`): `{ id: number; name: string; slug: string; description: string | null; location: string | null; starts_at: string; ends_at: string | null; status: string }` — hergebruikt in Task 10.

- [ ] **Step 1: Genereer Wayfinder-routes**

Run: `php artisan wayfinder:generate`
Expected: `resources/js/routes/competitions/index.ts` bestaat met o.a. `index`, `store`, `update`, `destroy`.

- [ ] **Step 2: Bouw het gedeelde formulier**

`resources/js/components/competitions/competition-form.tsx`:

```tsx
import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';

export type CompetitionProps = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string | null;
    status: string;
};

type Props = {
    competition?: CompetitionProps;
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
};

const STATUSES = ['draft', 'active', 'finished'] as const;

export default function CompetitionForm({
    competition,
    action,
    method,
    submitLabel,
}: Props) {
    const { t } = useTranslations();

    const statusLabels: Record<string, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (
        <Form
            action={action}
            method={method}
            className="flex max-w-xl flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">{t('Name')}</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            defaultValue={competition?.name ?? ''}
                        />
                        <InputError message={errors.name} />
                        <InputError message={errors.slug} />
                        {competition && (
                            <p className="text-muted-foreground text-sm">
                                {t('URL: :url', {
                                    url: `/${competition.slug}`,
                                })}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={4}
                            defaultValue={competition?.description ?? ''}
                            className="border-input bg-transparent placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px]"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="location">{t('Location')}</Label>
                        <Input
                            id="location"
                            name="location"
                            defaultValue={competition?.location ?? ''}
                        />
                        <InputError message={errors.location} />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="starts_at">{t('Start date')}</Label>
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="date"
                                required
                                defaultValue={competition?.starts_at ?? ''}
                            />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ends_at">{t('End date')}</Label>
                            <Input
                                id="ends_at"
                                name="ends_at"
                                type="date"
                                defaultValue={competition?.ends_at ?? ''}
                            />
                            <InputError message={errors.ends_at} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select
                            name="status"
                            defaultValue={competition?.status ?? 'draft'}
                        >
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabels[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
```

Controleer vóór gebruik de exacte props van `@/components/ui/select` en de `Form`-API (Inertia v3, zie de `inertia-react-development`-skill); pas aan waar de werkelijke component-API afwijkt (bijv. als `Select` geen `name` ondersteunt, gebruik dan een verborgen input of native `<select>` met dezelfde styling als `Input`).

- [ ] **Step 3: Bouw de pagina's**

`resources/js/pages/competitions/index.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, edit, index } from '@/routes/competitions';

type Props = {
    competitions: Array<CompetitionProps & { participants_count: number }>;
};

export default function CompetitionsIndex({ competitions }: Props) {
    const { t } = useTranslations();

    const statusLabels: Record<string, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (
        <>
            <Head title={t('Competitions')} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">
                        {t('Competitions')}
                    </h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('New competition')}
                        </Link>
                    </Button>
                </div>

                {competitions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {t('No competitions yet.')}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-3">{t('Name')}</th>
                                    <th className="p-3">{t('Status')}</th>
                                    <th className="p-3">{t('Start date')}</th>
                                    <th className="p-3">{t('Participants')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {competitions.map((competition) => (
                                    <tr
                                        key={competition.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">
                                            <Link
                                                href={edit(competition.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {competition.name}
                                            </Link>
                                        </td>
                                        <td className="p-3">
                                            <Badge variant="secondary">
                                                {
                                                    statusLabels[
                                                        competition.status
                                                    ]
                                                }
                                            </Badge>
                                        </td>
                                        <td className="p-3">
                                            {competition.starts_at}
                                        </td>
                                        <td className="p-3">
                                            {competition.participants_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

CompetitionsIndex.layout = {
    breadcrumbs: [{ title: 'Competities', href: index() }],
};
```

Let op: breadcrumb-titels volgen het bestaande patroon van `dashboard.tsx`. Check hoe `BreadcrumbItem.title` elders vertaald wordt; als daar geen `t()` gebruikt kán worden (statisch object), gebruik dan de Nederlandse titel consistent met de rest van de app.

`resources/js/pages/competitions/create.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import CompetitionForm from '@/components/competitions/competition-form';
import { useTranslations } from '@/hooks/use-translations';
import { create, index, store } from '@/routes/competitions';

export default function CompetitionsCreate() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('New competition')} />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">
                    {t('New competition')}
                </h1>
                <CompetitionForm
                    action={store().url}
                    method="post"
                    submitLabel={t('Create competition')}
                />
            </div>
        </>
    );
}

CompetitionsCreate.layout = {
    breadcrumbs: [
        { title: 'Competities', href: index() },
        { title: 'Nieuw', href: create() },
    ],
};
```

`resources/js/pages/competitions/edit.tsx`:

```tsx
import { Form, Head } from '@inertiajs/react';
import type { CompetitionProps } from '@/components/competitions/competition-form';
import CompetitionForm from '@/components/competitions/competition-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index, update } from '@/routes/competitions';

type Props = {
    competition: CompetitionProps;
};

export default function CompetitionsEdit({ competition }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={competition.name} />
            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">{competition.name}</h1>

                <CompetitionForm
                    competition={competition}
                    action={update(competition.id).url}
                    method="put"
                    submitLabel={t('Save changes')}
                />

                <div className="max-w-xl border-t pt-6">
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                {t('Delete competition')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {t('Delete competition?')}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'This removes the competition and its participant list. User accounts are kept.',
                                )}
                            </DialogDescription>
                            <DialogFooter>
                                <Form
                                    action={destroy(competition.id).url}
                                    method="delete"
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {t('Delete competition')}
                                        </Button>
                                    )}
                                </Form>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

CompetitionsEdit.layout = {
    breadcrumbs: [{ title: 'Competities', href: index() }],
};
```

Controleer de werkelijke exports van `@/components/ui/dialog` (bijv. `DialogHeader`) en pas de opbouw daarop aan.

- [ ] **Step 4: Vertalingen toevoegen**

Voeg toe aan `lang/nl.json` (bij de andere app-teksten):

```json
"Competitions": "Competities",
"New competition": "Nieuwe competitie",
"No competitions yet.": "Nog geen competities.",
"Name": "Naam",
"Status": "Status",
"Draft": "Concept",
"Active": "Actief",
"Finished": "Afgerond",
"Description": "Omschrijving",
"Location": "Locatie",
"Start date": "Startdatum",
"End date": "Einddatum",
"Participants": "Deelnemers",
"URL: :url": "URL: :url",
"Create competition": "Competitie aanmaken",
"Save changes": "Wijzigingen opslaan",
"Delete competition": "Competitie verwijderen",
"Delete competition?": "Competitie verwijderen?",
"This removes the competition and its participant list. User accounts are kept.": "Dit verwijdert de competitie en de deelnemerslijst. Gebruikersaccounts blijven bestaan."
```

- [ ] **Step 5: Navigatie-item toevoegen**

Voeg in `resources/js/components/app-sidebar.tsx` (check de bestaande structuur van nav-items) een item "Competities" toe dat naar `index()` uit `@/routes/competitions` linkt, met een passend lucide-icoon (bijv. `Trophy`). Volg exact het patroon van het bestaande Dashboard-item, inclusief vertaling.

- [ ] **Step 6: Checks + commit**

```bash
npm run check:fix && npm run types:check
```

Expected: geen errors/warnings.

```bash
git add -A && git commit -m "feat: beheer-UI voor competities"
```

---

### Task 7: Deelnemersgedeelte backend (middlewares, routes, controllers)

**Files:**

- Create: `app/Http/Middleware/EnsureCompetitionIsVisible.php`, `app/Http/Middleware/EnsureUserParticipatesInCompetition.php`
- Create: `app/Http/Controllers/Participant/CompetitionLoginController.php`, `app/Http/Controllers/Participant/CompetitionDashboardController.php`
- Modify: `routes/web.php`, `bootstrap/app.php`
- Test: `tests/Feature/CompetitionAccessTest.php`

**Interfaces:**

- Consumes: `Competition` + `CompetitionStatus` (Task 2), `User::isAdmin()` en `User::competitions()`.
- Produces: routes `competition.login` (`GET /{competition:slug}/login`) en `competition.dashboard` (`GET /{competition:slug}`); Inertia-componenten `auth/competition-login` (props: `competitionName`, `canResetPassword`, `status`) en `participant/dashboard` (props: `competition` `{name, slug, status, description, location, starts_at, ends_at}`, `participants` `Array<{id, name}>`). Gasten op deelnemer-routes worden naar `competition.login` geredirect i.p.v. `login`.

- [ ] **Step 1: Schrijf de failing tests**

`tests/Feature/CompetitionAccessTest.php`:

```php
<?php

use App\Models\Competition;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a linked participant can view the competition dashboard', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('participant/dashboard')
            ->where('competition.name', $competition->name)
            ->has('participants', 1),
        );
});

test('an unlinked participant gets a 403', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertForbidden();
});

test('admins can view any competition dashboard', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('guests are redirected to the competition login page', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.dashboard', $competition))
        ->assertRedirect(route('competition.login', $competition));
});

test('the competition login page renders for guests', function () {
    $competition = Competition::factory()->create();

    $this->get(route('competition.login', $competition))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/competition-login')
            ->where('competitionName', $competition->name),
        );
});

test('an authenticated participant visiting the login page is sent to the dashboard', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.login', $competition))
        ->assertRedirect(route('competition.dashboard', $competition));
});

test('draft competitions are hidden from participants and guests', function () {
    $competition = Competition::factory()->draft()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->get(route('competition.login', $competition))->assertNotFound();
    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertNotFound();
});

test('draft competitions remain visible for admins', function () {
    $competition = Competition::factory()->draft()->create();

    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('finished competitions remain accessible for participants', function () {
    $competition = Competition::factory()->finished()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertOk();
});

test('an unknown slug returns a 404', function () {
    $this->get('/bestaat-niet')->assertNotFound();
});

test('participants only see id and name of other participants', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->actingAs($user)
        ->get(route('competition.dashboard', $competition))
        ->assertInertia(fn (Assert $page) => $page
            ->has('participants.0', fn (Assert $participant) => $participant
                ->has('id')
                ->has('name'),
            ),
        );
});
```

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/CompetitionAccessTest.php`
Expected: FAIL (routes bestaan niet).

- [ ] **Step 3: Implementeer middlewares**

`app/Http/Middleware/EnsureCompetitionIsVisible.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verbergt concept-competities voor iedereen behalve admins. Actieve en
 * afgeronde competities zijn zichtbaar.
 */
class EnsureCompetitionIsVisible
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $competition = $request->route('competition');

        abort_unless($competition instanceof Competition, 404);

        $user = $request->user();
        $isAdmin = $user instanceof User && $user->isAdmin();

        abort_if($competition->status === CompetitionStatus::Draft && ! $isAdmin, 404);

        return $next($request);
    }
}
```

`app/Http/Middleware/EnsureUserParticipatesInCompetition.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Competition;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laat alleen aan de competitie gekoppelde gebruikers (en admins) door naar
 * het deelnemersgedeelte.
 */
class EnsureUserParticipatesInCompetition
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $competition = $request->route('competition');

        abort_unless($competition instanceof Competition, 404);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->isAdmin() || $competition->participants()->whereKey($user->getKey())->exists()) {
            return $next($request);
        }

        abort(403);
    }
}
```

- [ ] **Step 4: Implementeer controllers**

`app/Http/Controllers/Participant/CompetitionLoginController.php`:

```php
<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class CompetitionLoginController extends Controller
{
    /**
     * Toon de competitie-loginpagina en zet de intended-url zodat de
     * bestaande Fortify-pipeline na inloggen terugstuurt naar de competitie.
     */
    public function __invoke(Request $request, Competition $competition): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('competition.dashboard', $competition);
        }

        $request->session()->put('url.intended', route('competition.dashboard', $competition));

        return Inertia::render('auth/competition-login', [
            'competitionName' => $competition->name,
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]);
    }
}
```

`app/Http/Controllers/Participant/CompetitionDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionDashboardController extends Controller
{
    public function __invoke(Competition $competition): Response
    {
        return Inertia::render('participant/dashboard', [
            'competition' => [
                'name' => $competition->name,
                'slug' => $competition->slug,
                'status' => $competition->status->value,
                'description' => $competition->description,
                'location' => $competition->location,
                'starts_at' => $competition->starts_at->toDateString(),
                'ends_at' => $competition->ends_at?->toDateString(),
            ],
            'participants' => $competition->participants()
                ->orderBy('name')
                ->get()
                ->map(fn (User $participant): array => [
                    'id' => $participant->id,
                    'name' => $participant->name,
                ])
                ->all(),
        ]);
    }
}
```

- [ ] **Step 5: Routes + guest-redirect**

Onderaan `routes/web.php`, NA `require __DIR__.'/settings.php';` (deze groep moet als allerlaatste geregistreerd worden zodat vaste routes voorrang houden):

```php
Route::prefix('{competition:slug}')
    ->middleware(EnsureCompetitionIsVisible::class)
    ->group(function () {
        Route::get('login', CompetitionLoginController::class)
            ->name('competition.login');

        Route::get('/', CompetitionDashboardController::class)
            ->middleware(EnsureUserParticipatesInCompetition::class)
            ->name('competition.dashboard');
    });
```

(imports: `App\Http\Controllers\Participant\CompetitionLoginController`, `App\Http\Controllers\Participant\CompetitionDashboardController`, `App\Http\Middleware\EnsureCompetitionIsVisible`, `App\Http\Middleware\EnsureUserParticipatesInCompetition`).

**Let op — gastafhandeling zonder `auth`-middleware.** De dashboard-route draagt bewust GEEN `auth`. Reden: `auth` heeft één globaal redirect-doel en kan niet competitiebewust zijn, en Laravel's default-prioriteit draait `auth` vóór `SubstituteBindings`, waardoor de route-parameter bij een gast nog een string is. Een globale `redirectGuestsTo`-callback of een herdefinitie van `$middleware->priority([...])` lost dat wel op, maar verandert het gedrag van élke web-route met route-model-binding (o.a. een anoniem existentie-orakel op `competitions/{id}/edit`) en is daarom bewust verworpen.

In plaats daarvan is `EnsureUserParticipatesInCompetition` de toegangspoort van het deelnemersgedeelte: die middleware staat niet in Laravel's prioriteitsmap, behoudt dus zijn natuurlijke positie ná `SubstituteBindings`, en stuurt gasten zelf door:

```php
$user = $request->user();

if ($user === null) {
    return redirect()->route('competition.login', $competition);
}
```

`bootstrap/app.php` blijft hierdoor volledig ongewijzigd. Omdat `EnsureCompetitionIsVisible` op de routegroep hangt, draait die vóór deze middleware: een gast op een `Draft`-competitie krijgt dus een 404 en geen redirect, waardoor concept-competities voor anonieme bezoekers ononderscheidbaar blijven van niet-bestaande.

Maak tijdelijk een minimale placeholder `resources/js/pages/participant/dashboard.tsx` en `resources/js/pages/auth/competition-login.tsx` zodat Vite/tsc niet breekt (feature tests renderen geen JS, maar `npm run types:check` wel):

```tsx
// resources/js/pages/participant/dashboard.tsx — wordt in Task 11 vervangen
export default function ParticipantDashboard() {
    return null;
}
```

```tsx
// resources/js/pages/auth/competition-login.tsx — wordt in Task 11 vervangen
export default function CompetitionLogin() {
    return null;
}

CompetitionLogin.layout = { label: 'participant-login' };
```

- [ ] **Step 6: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/CompetitionAccessTest.php`
Expected: PASS (11 tests).

Regressiecheck: `php artisan test --compact tests/Feature/Auth/AuthenticationTest.php`
Expected: PASS (guest-redirect naar `login` werkt nog voor niet-competitieroutes).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: deelnemersgedeelte-routes met zichtbaarheids- en membership-middleware"
```

---

### Task 8: Rolafhankelijke LoginResponse + geen-competitie-pagina

**Files:**

- Create: `app/Http/Responses/LoginResponse.php`
- Modify: `app/Providers/FortifyServiceProvider.php`, `routes/web.php`
- Create: `resources/js/pages/participant/no-competition.tsx` (placeholder; definitief in Task 11)
- Test: `tests/Feature/Auth/ParticipantLoginTest.php`

**Interfaces:**

- Consumes: `competition.dashboard`/`competition.login`-routes (Task 7), `User::competitions()`, `CompetitionStatus`.
- Produces: `App\Http\Responses\LoginResponse` gebonden aan zowel `Laravel\Fortify\Contracts\LoginResponse` als `Laravel\Fortify\Contracts\TwoFactorLoginResponse`; route `competition.none` (`GET /no-competition`, auth) die `participant/no-competition` rendert.

- [ ] **Step 1: Schrijf de failing tests**

`tests/Feature/Auth/ParticipantLoginTest.php`:

```php
<?php

use App\Http\Responses\LoginResponse;
use App\Models\Competition;
use App\Models\User;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

test('admins are redirected to the admin dashboard after login', function () {
    // Admin zonder 2FA: die doorloopt de pipeline zonder challenge en raakt
    // direct de LoginResponse (de 2FA-setup-redirect gebeurt pas daarna via
    // de EnsureTwoFactorIsConfigured-middleware, niet in de login-redirect).
    $admin = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('participants are redirected to their most recent active competition', function () {
    $user = User::factory()->participant()->create();
    $old = Competition::factory()->create(['starts_at' => now()->subMonth()->toDateString()]);
    $recent = Competition::factory()->create(['starts_at' => now()->toDateString()]);
    $finished = Competition::factory()->finished()->create(['starts_at' => now()->addDay()->toDateString()]);
    $user->competitions()->attach([$old->id, $recent->id, $finished->id]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.dashboard', $recent));
});

test('participants without an active competition see the no-competition page', function () {
    $user = User::factory()->participant()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.none'));

    $this->get(route('competition.none'))->assertOk();
});

test('logging in via the competition login page returns to that competition', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->get(route('competition.login', $competition));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('competition.dashboard', $competition));
});

test('the custom login response is bound for both login contracts', function () {
    expect(app(LoginResponseContract::class))->toBeInstanceOf(LoginResponse::class)
        ->and(app(TwoFactorLoginResponseContract::class))->toBeInstanceOf(LoginResponse::class);
});
```

Controleer eerst met `php artisan route:list --name=login` hoe de login-POST-route heet (`login.store` of `login`); pas de tests hierop aan.

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/Auth/ParticipantLoginTest.php`
Expected: FAIL (LoginResponse-klasse en `competition.none` bestaan niet).

- [ ] **Step 3: Implementeer de response en route**

`app/Http/Responses/LoginResponse.php`:

```php
<?php

namespace App\Http\Responses;

use App\Enums\CompetitionStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * Rolafhankelijke redirect na inloggen: admins naar het beheer-dashboard,
 * deelnemers naar hun meest recente actieve competitie. Een intended-url
 * (zoals gezet door de competitie-loginpagina) heeft altijd voorrang.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        return redirect()->intended($this->defaultUrlFor($user instanceof User ? $user : null));
    }

    protected function defaultUrlFor(?User $user): string
    {
        if ($user === null || $user->isAdmin()) {
            return route('dashboard');
        }

        $competition = $user->competitions()
            ->where('status', CompetitionStatus::Active)
            ->orderByDesc('starts_at')
            ->first();

        return $competition !== null
            ? route('competition.dashboard', $competition)
            : route('competition.none');
    }
}
```

In `app/Providers/FortifyServiceProvider.php`, in `register()`:

```php
$this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\LoginResponse::class);
$this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\LoginResponse::class);
```

Check daarnaast of Fortify een aparte passkey-loginresponse kent: `ls vendor/laravel/fortify/src/Contracts | grep -i login`. Bestaat er bijv. een `PasskeyLoginResponse`-contract, bind dan ook die aan dezelfde klasse.

Route in `routes/web.php` (binnen de bestaande `Route::middleware('auth')`-groep, dus vóór de competitie-wildcardgroep):

```php
Route::inertia('no-competition', 'participant/no-competition')->name('competition.none');
```

Placeholder `resources/js/pages/participant/no-competition.tsx`:

```tsx
// Wordt in Task 11 vervangen door de definitieve pagina.
export default function NoCompetition() {
    return null;
}
```

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/Auth/ParticipantLoginTest.php`
Expected: PASS (5 tests).

Regressiecheck (alle bestaande login-/2FA-flows): `php artisan test --compact tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: rolafhankelijke login-redirects via custom LoginResponse"
```

---

### Task 9: Invitations uitbreiden met rol + competitie

**Files:**

- Create: migratie via `php artisan make:migration add_competition_and_role_to_invitations_table --no-interaction`
- Create: `app/Actions/Auth/SendInvitation.php`
- Modify: `app/Models/Invitation.php`, `app/Models/Competition.php`, `app/Notifications/InvitationNotification.php`, `app/Http/Controllers/Auth/InvitationController.php`, `app/Console/Commands/InviteUser.php`, `database/factories/InvitationFactory.php`, `lang/nl.json`
- Test: `tests/Feature/Auth/InvitationTest.php` (uitbreiden), `tests/Feature/Auth/InviteUserCommandTest.php` (regressie)

**Interfaces:**

- Consumes: `Competition`, `UserRole`, `competition.dashboard`-route (Task 7).
- Produces: `Invitation::$competition_id` (nullable FK), `Invitation::$role` (cast `UserRole`, DB-default `'admin'`), `Invitation::competition(): BelongsTo`, `Competition::invitations(): HasMany`, `App\Actions\Auth\SendInvitation::handle(string $email, UserRole $role, ?Competition $competition = null, ?User $inviter = null): string` (retourneert de plain token en verstuurt de mail). Acceptatie zet de rol van de uitnodiging op de nieuwe user, koppelt de competitie en redirect participants naar hun competitie.

- [ ] **Step 1: Schrijf de failing tests**

Voeg toe aan `tests/Feature/Auth/InvitationTest.php` (bekijk eerst hoe de bestaande tests een geldige invitation + token opbouwen en volg dat patroon exact — vermoedelijk `Invitation::factory()` met een bekende plain token):

```php
test('accepting a participant invitation creates a participant linked to the competition', function () {
    $competition = Competition::factory()->create();
    $plainToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
        'competition_id' => $competition->id,
        'role' => UserRole::Participant,
    ]);

    $response = $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Deelnemer',
        'password' => 'SuperSecret123!',
        'password_confirmation' => 'SuperSecret123!',
    ]);

    $user = User::query()->where('email', $invitation->email)->firstOrFail();

    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->competitions()->whereKey($competition->id)->exists())->toBeTrue();

    $response->assertRedirect(route('competition.dashboard', $competition));
});

test('accepting an admin invitation still redirects to two factor setup', function () {
    $plainToken = Str::random(64);
    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
    ]);

    $this->post(route('invitation.store', $plainToken), [
        'name' => 'Nieuwe Beheerder',
        'password' => 'SuperSecret123!',
        'password_confirmation' => 'SuperSecret123!',
    ])->assertRedirect(route('two-factor.setup'));

    expect(User::query()->where('email', $invitation->email)->firstOrFail()->role)
        ->toBe(UserRole::Admin);
});
```

(imports: `App\Enums\UserRole`, `App\Models\Competition`, `Illuminate\Support\Str`; wachtwoorden aanpassen aan wat de bestaande tests gebruiken zodat ze door `Password::defaults()` komen.)

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/Auth/InvitationTest.php`
Expected: FAIL (kolommen bestaan niet).

- [ ] **Step 3: Implementeer migratie en modellen**

Migratie:

```php
<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('competition_id')
                ->nullable()
                ->after('invited_by')
                ->constrained()
                ->cascadeOnDelete();
            // Bestaande uitnodigingen zijn admin-uitnodigingen.
            $table->string('role')->default(UserRole::Admin->value)->after('competition_id');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competition_id');
            $table->dropColumn('role');
        });
    }
};
```

`app/Models/Invitation.php`: voeg `'competition_id'` en `'role'` toe aan `#[Fillable]`, PHPDoc-props `@property int|null $competition_id`, `@property UserRole $role`, `@property-read Competition|null $competition`; cast `'role' => UserRole::class`; relatie:

```php
/**
 * @return BelongsTo<Competition, $this>
 */
public function competition(): BelongsTo
{
    return $this->belongsTo(Competition::class);
}
```

`app/Models/Competition.php`: relatie toevoegen:

```php
/**
 * @return HasMany<Invitation, $this>
 */
public function invitations(): HasMany
{
    return $this->hasMany(Invitation::class);
}
```

`database/factories/InvitationFactory.php`: bekijk de bestaande definition; `role` hoeft geen default in de factory (DB-default `admin` volstaat), maar controleer dat de factory geen kolommen mist.

- [ ] **Step 4: Implementeer SendInvitation-action + command-refactor**

`app/Actions/Auth/SendInvitation.php`:

```php
<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Maakt (of vervangt) een uitnodiging en verstuurt de uitnodigingsmail.
 */
class SendInvitation
{
    /**
     * Hoe lang een uitnodiging geldig blijft, in dagen.
     */
    protected int $expiresAfterDays = 7;

    /**
     * Retourneert de plain token voor de accept-link.
     */
    public function handle(string $email, UserRole $role, ?Competition $competition = null, ?User $inviter = null): string
    {
        Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        $plainToken = Str::random(64);

        $invitation = Invitation::create([
            'email' => $email,
            'token' => hash('sha256', $plainToken),
            'invited_by' => $inviter?->id,
            'competition_id' => $competition?->id,
            'role' => $role,
            'expires_at' => now()->addDays($this->expiresAfterDays),
        ]);

        Notification::route('mail', $email)
            ->notify(new InvitationNotification($invitation, $plainToken));

        return $plainToken;
    }
}
```

Refactor `app/Console/Commands/InviteUser.php`: vervang het blok vanaf `Invitation::query()->...delete()` t/m de `Notification::route(...)`-call door:

```php
$plainToken = app(SendInvitation::class)->handle($email, UserRole::Admin);
```

(imports: `App\Actions\Auth\SendInvitation`, `App\Enums\UserRole`; property `$expiresAfterDays` en ongebruikte imports uit het command verwijderen; de `info`/`line`-output blijft gelijk.)

- [ ] **Step 5: Notification + acceptflow aanpassen**

`app/Notifications/InvitationNotification.php` — vervang `toMail()`:

```php
public function toMail(object $notifiable): MailMessage
{
    $competition = $this->invitation->competition;

    $mail = (new MailMessage)
        ->subject(__("You're invited to :app", ['app' => config('app.name')]));

    if ($competition !== null) {
        $mail->line(__('You have been invited to join the competition :competition.', [
            'competition' => $competition->name,
        ]));
    } else {
        $mail->line(__('You have been invited to the :app admin console.', ['app' => config('app.name')]));
    }

    return $mail
        ->action(__('Accept invitation'), route('invitation.show', $this->plainToken))
        ->line(__('This invitation is valid until :date.', [
            'date' => $this->invitation->expires_at->translatedFormat('j F Y H:i'),
        ]))
        ->line(__('If you did not expect this invitation, you can ignore this email.'));
}
```

`lang/nl.json`:

```json
"You have been invited to join the competition :competition.": "Je bent uitgenodigd om deel te nemen aan de competitie :competition."
```

`app/Http/Controllers/Auth/InvitationController.php` — vervang in `store()` het transaction-blok en de redirect:

```php
$user = DB::transaction(function () use ($invitation, $request): User {
    $invitation->forceFill(['accepted_at' => now()])->save();

    $user = User::create([
        'name' => $request->string('name')->toString(),
        'email' => $invitation->email,
        'password' => $request->string('password')->toString(),
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
        'role' => $invitation->role,
    ])->save();

    if ($invitation->competition_id !== null) {
        $user->competitions()->attach($invitation->competition_id);
    }

    return $user;
});

Auth::login($user);

$request->session()->regenerate();
$request->session()->put('auth.password_confirmed_at', time());

if ($user->isAdmin()) {
    return redirect()->route('two-factor.setup');
}

return $invitation->competition !== null
    ? redirect()->route('competition.dashboard', $invitation->competition)
    : redirect()->route('competition.none');
```

- [ ] **Step 6: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/Auth/InvitationTest.php tests/Feature/Auth/InviteUserCommandTest.php`
Expected: PASS (inclusief alle bestaande invitation-tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: uitnodigingen met rol en competitie-koppeling"
```

---

### Task 10: Deelnemersbeheer per competitie (backend + UI)

**Files:**

- Create: `app/Http/Requests/Competitions/StoreCompetitionParticipantRequest.php`, `app/Http/Controllers/CompetitionParticipantController.php`
- Create: `resources/js/components/competitions/participant-manager.tsx`
- Modify: `app/Http/Controllers/CompetitionController.php` (edit-props), `routes/web.php`, `resources/js/pages/competitions/edit.tsx`, `lang/nl.json`
- Test: `tests/Feature/CompetitionParticipantManagementTest.php`

**Interfaces:**

- Consumes: `SendInvitation` (Task 9), `Competition::participants()`, `Competition::invitations()`, `Invitation::scopePending()`, admin-routegroep, `PasswordValidationRules`-concern (`app/Concerns/PasswordValidationRules.php`).
- Produces: routes `competitions.participants.store` (`POST competitions/{competition}/participants`) en `competitions.participants.destroy` (`DELETE competitions/{competition}/participants/{user}`). Request-velden: `email` (verplicht); als het e-mailadres onbekend is ook `mode` (`invite`|`create`) en bij `create` ook `name` + `password`. `CompetitionController::edit` levert extra props `participants` (`Array<{id, name, email, is_admin}>`) en `pendingInvitations` (`Array<{id, email, expires_at}>`).

- [ ] **Step 1: Schrijf de failing tests**

`tests/Feature/CompetitionParticipantManagementTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

test('an existing user is linked directly by email', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
    ])->assertRedirect();

    expect($competition->participants()->whereKey($user->id)->exists())->toBeTrue();
});

test('linking an existing admin keeps the admin role', function () {
    $competition = Competition::factory()->create();
    $admin = User::factory()->withTwoFactor()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $admin->email,
    ]);

    expect($admin->refresh()->role)->toBe(UserRole::Admin)
        ->and($competition->participants()->whereKey($admin->id)->exists())->toBeTrue();
});

test('linking the same user twice does not fail or duplicate', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
    ])->assertRedirect();

    expect($competition->participants()->count())->toBe(1);
});

test('an unknown email with invite mode sends a participant invitation', function () {
    Notification::fake();
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'nieuw@example.com',
        'mode' => 'invite',
    ])->assertRedirect();

    $invitation = Invitation::query()->where('email', 'nieuw@example.com')->firstOrFail();
    expect($invitation->role)->toBe(UserRole::Participant)
        ->and($invitation->competition_id)->toBe($competition->id);

    Notification::assertSentOnDemand(InvitationNotification::class);
});

test('an unknown email with create mode creates a verified participant account', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'direct@example.com',
        'mode' => 'create',
        'name' => 'Directe Deelnemer',
        'password' => 'SuperSecret123!',
    ])->assertRedirect();

    $user = User::query()->where('email', 'direct@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($competition->participants()->whereKey($user->id)->exists())->toBeTrue();
});

test('create mode requires a name and password', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'direct@example.com',
        'mode' => 'create',
    ])->assertSessionHasErrors(['name', 'password']);
});

test('an unknown email without a mode is rejected', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'nieuw@example.com',
    ])->assertSessionHasErrors('mode');
});

test('a participant can be detached', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);

    $this->delete(route('competitions.participants.destroy', [$competition, $user]))
        ->assertRedirect();

    expect($competition->participants()->count())->toBe(0)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

test('participants cannot manage the participant list', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->post(route('competitions.participants.store', $competition), [
            'email' => 'x@example.com',
            'mode' => 'invite',
        ])->assertForbidden();
});
```

(Wachtwoord `'SuperSecret123!'` aanpassen als `Password::defaults()` strenger blijkt; check wat bestaande tests gebruiken.)

- [ ] **Step 2: Run de test — verwacht FAIL**

Run: `php artisan test --compact tests/Feature/CompetitionParticipantManagementTest.php`
Expected: FAIL (routes bestaan niet).

- [ ] **Step 3: Implementeer request en controller**

`app/Http/Requests/Competitions/StoreCompetitionParticipantRequest.php`:

```php
<?php

namespace App\Http\Requests\Competitions;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetitionParticipantRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userExists = User::query()->where('email', $this->input('email'))->exists();
        $isCreating = $this->input('mode') === 'create';

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'mode' => [Rule::excludeIf($userExists), 'required', Rule::in(['invite', 'create'])],
            'name' => [Rule::excludeIf($userExists || ! $isCreating), 'required', 'string', 'max:255'],
            'password' => [Rule::excludeIf($userExists || ! $isCreating), ...$this->passwordRules()],
        ];
    }
}
```

Check de exacte signatuur van `PasswordValidationRules::passwordRules()` (bevat die al `required` en `confirmed`?). Bevat die `confirmed`, laat de confirmation-eis hier weg door de regels handmatig samen te stellen (`['required', 'string', Password::defaults()]`) — de beheerder vult één wachtwoordveld in.

`app/Http/Controllers/CompetitionParticipantController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SendInvitation;
use App\Enums\UserRole;
use App\Http\Requests\Competitions\StoreCompetitionParticipantRequest;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CompetitionParticipantController extends Controller
{
    public function store(StoreCompetitionParticipantRequest $request, Competition $competition, SendInvitation $sendInvitation): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $competition->participants()->syncWithoutDetaching([$existing->id]);

            return back();
        }

        if ($request->string('mode')->toString() === 'invite') {
            $sendInvitation->handle($email, UserRole::Participant, $competition, $request->user());

            return back();
        }

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $competition->participants()->attach($user);

        return back();
    }

    public function destroy(Competition $competition, User $user): RedirectResponse
    {
        $competition->participants()->detach($user);

        return back();
    }
}
```

Routes (in de admin-groep, na de resource-route):

```php
Route::post('competitions/{competition}/participants', [CompetitionParticipantController::class, 'store'])
    ->name('competitions.participants.store');
Route::delete('competitions/{competition}/participants/{user}', [CompetitionParticipantController::class, 'destroy'])
    ->name('competitions.participants.destroy');
```

`CompetitionController::edit()` uitbreiden met deelnemers en openstaande uitnodigingen:

```php
public function edit(Competition $competition): Response
{
    return Inertia::render('competitions/edit', [
        'competition' => $this->competitionProps($competition),
        'participants' => $competition->participants()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
            ])
            ->all(),
        'pendingInvitations' => $competition->invitations()
            ->pending()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toDateString(),
            ])
            ->all(),
    ]);
}
```

(imports: `App\Models\User`, `App\Models\Invitation`.)

- [ ] **Step 4: Run de tests — verwacht PASS**

Run: `php artisan test --compact tests/Feature/CompetitionParticipantManagementTest.php tests/Feature/CompetitionManagementTest.php`
Expected: PASS.

- [ ] **Step 5: Bouw de participant-manager-UI**

Run eerst: `php artisan wayfinder:generate`

`resources/js/components/competitions/participant-manager.tsx`:

```tsx
import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import {
    destroy as destroyParticipant,
    store as storeParticipant,
} from '@/routes/competitions/participants';

export type ParticipantProps = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
};

export type PendingInvitationProps = {
    id: number;
    email: string;
    expires_at: string;
};

type Props = {
    competitionId: number;
    participants: ParticipantProps[];
    pendingInvitations: PendingInvitationProps[];
};

export default function ParticipantManager({
    competitionId,
    participants,
    pendingInvitations,
}: Props) {
    const { t } = useTranslations();
    const [mode, setMode] = useState<'invite' | 'create'>('invite');

    return (
        <section className="flex max-w-xl flex-col gap-4">
            <h2 className="text-lg font-semibold">{t('Participants')}</h2>

            {participants.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('No participants yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {participants.map((participant) => (
                        <li
                            key={participant.id}
                            className="flex items-center justify-between gap-2 p-3"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {participant.name}
                                    {participant.is_admin && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {t('Admin')}
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-muted-foreground truncate text-sm">
                                    {participant.email}
                                </p>
                            </div>
                            <Form
                                action={
                                    destroyParticipant([
                                        competitionId,
                                        participant.id,
                                    ]).url
                                }
                                method="delete"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {t('Remove')}
                                    </Button>
                                )}
                            </Form>
                        </li>
                    ))}
                </ul>
            )}

            {pendingInvitations.length > 0 && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-sm font-medium">
                        {t('Pending invitations')}
                    </h3>
                    <ul className="divide-y rounded-xl border">
                        {pendingInvitations.map((invitation) => (
                            <li
                                key={invitation.id}
                                className="flex items-center justify-between p-3 text-sm"
                            >
                                <span>{invitation.email}</span>
                                <span className="text-muted-foreground">
                                    {t('Valid until :date', {
                                        date: invitation.expires_at,
                                    })}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Form
                action={storeParticipant(competitionId).url}
                method="post"
                resetOnSuccess
                className="flex flex-col gap-4 rounded-xl border p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="participant-email">
                                {t('Email address')}
                            </Label>
                            <Input
                                id="participant-email"
                                name="email"
                                type="email"
                                required
                            />
                            <InputError message={errors.email} />
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    'An existing account is linked directly. For a new email address, choose how the account is created.',
                                )}
                            </p>
                        </div>

                        <div className="flex gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="mode"
                                    value="invite"
                                    checked={mode === 'invite'}
                                    onChange={() => setMode('invite')}
                                />
                                {t('Send invitation email')}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="mode"
                                    value="create"
                                    checked={mode === 'create'}
                                    onChange={() => setMode('create')}
                                />
                                {t('Create account directly')}
                            </label>
                        </div>
                        <InputError message={errors.mode} />

                        {mode === 'create' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-name">
                                        {t('Name')}
                                    </Label>
                                    <Input id="participant-name" name="name" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="participant-password">
                                        {t('Password')}
                                    </Label>
                                    <Input
                                        id="participant-password"
                                        name="password"
                                        type="password"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                            </div>
                        )}

                        <div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Add participant')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
```

Controleer de Wayfinder-import: bij routenamen `competitions.participants.store`/`.destroy` genereert Wayfinder `@/routes/competitions/participants`. Check het gegenereerde bestand voor de exacte parametervorm (`destroyParticipant({ competition: id, user: id })` of positioneel) en pas de aanroepen aan.

In `resources/js/pages/competitions/edit.tsx`: props uitbreiden en de manager onder het formulier renderen (boven de delete-sectie):

```tsx
type Props = {
    competition: CompetitionProps;
    participants: ParticipantProps[];
    pendingInvitations: PendingInvitationProps[];
};
```

```tsx
<ParticipantManager
    competitionId={competition.id}
    participants={participants}
    pendingInvitations={pendingInvitations}
/>
```

(imports uit `@/components/competitions/participant-manager`.)

- [ ] **Step 6: Vertalingen toevoegen**

`lang/nl.json`:

```json
"No participants yet.": "Nog geen deelnemers.",
"Admin": "Beheerder",
"Remove": "Verwijderen",
"Pending invitations": "Openstaande uitnodigingen",
"Valid until :date": "Geldig tot :date",
"An existing account is linked directly. For a new email address, choose how the account is created.": "Een bestaand account wordt direct gekoppeld. Kies voor een nieuw e-mailadres hoe het account wordt aangemaakt.",
"Send invitation email": "Stuur uitnodigingsmail",
"Create account directly": "Account direct aanmaken",
"Add participant": "Deelnemer toevoegen"
```

- [ ] **Step 7: Checks + commit**

```bash
npm run check:fix && npm run types:check
php artisan test --compact tests/Feature/CompetitionParticipantManagementTest.php
```

Expected: geen lint-/type-errors, tests PASS.

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: deelnemersbeheer per competitie"
```

---

### Task 11: Deelnemersgedeelte frontend (mobile-first) + competitie-login

**Files:**

- Create: `resources/js/layouts/participant/participant-layout.tsx`, `resources/js/components/console/login-form.tsx`
- Replace: `resources/js/pages/auth/competition-login.tsx`, `resources/js/pages/participant/dashboard.tsx`, `resources/js/pages/participant/no-competition.tsx`
- Modify: `resources/js/pages/auth/login.tsx` (refactor naar gedeeld formulier), `resources/js/app.tsx`, `lang/nl.json`

**Interfaces:**

- Consumes: props van `CompetitionLoginController` en `CompetitionDashboardController` (Task 7), console-componenten, `logout`-route uit `@/routes`.
- Produces: `LoginForm`-component (`{ canResetPassword: boolean }`) gedeeld door beide loginpagina's; `ParticipantLayout` als layout voor alle `participant/*`-pagina's.

- [ ] **Step 1: Extraheer het gedeelde loginformulier**

Maak `resources/js/components/console/login-form.tsx` door uit `resources/js/pages/auth/login.tsx` alles vanaf het `<Form>`-element t/m de passkey-sectie te verplaatsen naar een component:

```tsx
type Props = {
    canResetPassword: boolean;
};

export default function LoginForm({ canResetPassword }: Props) {
    /* de verplaatste JSX */
}
```

Refactor `resources/js/pages/auth/login.tsx` zodat die `<LoginForm canResetPassword={canResetPassword} />` rendert onder de bestaande `ConsoleHeading` + status-melding; gedrag en animatie-delays blijven identiek. De 2FA-footerregel ("Secured with 2FA …") blijft in `login.tsx` (die claim geldt alleen voor het beheer).

- [ ] **Step 2: Bouw de competitie-loginpagina**

`resources/js/pages/auth/competition-login.tsx` (vervang de placeholder):

```tsx
import { Head } from '@inertiajs/react';
import LoginForm from '@/components/console/login-form';
import ConsoleHeading from '@/components/console/console-heading';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    competitionName: string;
    status?: string;
    canResetPassword: boolean;
};

export default function CompetitionLogin({
    competitionName,
    status,
    canResetPassword,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={competitionName} />

            <ConsoleHeading
                title={competitionName}
                typewriter={t('> participant login')}
            />

            {status && (
                <p className="text-console-success made-anim mb-4 text-center text-sm">
                    {status}
                </p>
            )}

            <LoginForm canResetPassword={canResetPassword} />
        </>
    );
}

CompetitionLogin.layout = { label: 'participant-login' };
```

- [ ] **Step 3: Bouw de participant-layout (mobile-first)**

`resources/js/layouts/participant/participant-layout.tsx`:

```tsx
import { Form, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { logout } from '@/routes';

type ParticipantPageProps = {
    competition?: { name: string };
};

export default function ParticipantLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { t } = useTranslations();
    const { competition } = usePage().props as ParticipantPageProps;

    return (
        <div className="bg-background text-foreground flex min-h-svh flex-col">
            <header className="bg-background/95 sticky top-0 z-10 border-b backdrop-blur">
                <div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-3 px-4 py-3">
                    <div className="flex min-w-0 items-center gap-2">
                        <AppLogoIcon className="size-7 shrink-0" />
                        {competition && (
                            <span className="truncate text-sm font-semibold">
                                {competition.name}
                            </span>
                        )}
                    </div>
                    <Form action={logout().url} method="post">
                        {() => (
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                aria-label={t('Log out')}
                            >
                                <LogOut />
                                <span className="hidden sm:inline">
                                    {t('Log out')}
                                </span>
                            </Button>
                        )}
                    </Form>
                </div>
            </header>
            <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-4 sm:py-6">
                {children}
            </main>
        </div>
    );
}
```

Check hoe `logout` in Wayfinder heet (`@/routes` exporteert vermoedelijk `logout`; check `resources/js/routes/index.ts`) en of `AppLogoIcon` een `className` accepteert.

In `resources/js/app.tsx` een case toevoegen vóór de default:

```tsx
case name.startsWith('participant/'):
    return ParticipantLayout;
```

(import `ParticipantLayout from '@/layouts/participant/participant-layout'`.)

- [ ] **Step 4: Bouw het deelnemersdashboard (mobile-first)**

`resources/js/pages/participant/dashboard.tsx` (vervang de placeholder):

```tsx
import { Head } from '@inertiajs/react';
import { CalendarDays, MapPin, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    competition: {
        name: string;
        slug: string;
        status: string;
        description: string | null;
        location: string | null;
        starts_at: string;
        ends_at: string | null;
    };
    participants: Array<{ id: number; name: string }>;
};

export default function ParticipantDashboard({
    competition,
    participants,
}: Props) {
    const { t } = useTranslations();

    const statusLabels: Record<string, string> = {
        draft: t('Draft'),
        active: t('Active'),
        finished: t('Finished'),
    };

    return (
        <>
            <Head title={competition.name} />

            <div className="flex flex-col gap-4">
                <section className="flex flex-col gap-2 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-lg font-semibold sm:text-xl">
                            {competition.name}
                        </h1>
                        <Badge variant="secondary">
                            {statusLabels[competition.status]}
                        </Badge>
                    </div>

                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <CalendarDays className="size-4 shrink-0" />
                        {competition.ends_at
                            ? t(':from until :until', {
                                  from: competition.starts_at,
                                  until: competition.ends_at,
                              })
                            : competition.starts_at}
                    </p>

                    {competition.location && (
                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                            <MapPin className="size-4 shrink-0" />
                            {competition.location}
                        </p>
                    )}

                    {competition.description && (
                        <p className="mt-2 text-sm leading-relaxed">
                            {competition.description}
                        </p>
                    )}
                </section>

                <section className="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Users className="size-4" />
                        {t('Participants')}
                        <span className="text-muted-foreground text-sm font-normal">
                            ({participants.length})
                        </span>
                    </h2>
                    {participants.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('No participants yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {participants.map((participant) => (
                                <li
                                    key={participant.id}
                                    className="py-2 text-sm"
                                >
                                    {participant.name}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
```

`resources/js/pages/participant/no-competition.tsx` (vervang de placeholder):

```tsx
import { Head } from '@inertiajs/react';
import { useTranslations } from '@/hooks/use-translations';

export default function NoCompetition() {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('No active competition')} />
            <div className="flex flex-col items-center gap-2 py-16 text-center">
                <h1 className="text-lg font-semibold">
                    {t('No active competition')}
                </h1>
                <p className="text-muted-foreground max-w-sm text-sm">
                    {t(
                        'You are not linked to an active competition right now. Contact the organizer if you expected one.',
                    )}
                </p>
            </div>
        </>
    );
}
```

(De uitlogknop zit al in de `ParticipantLayout`-header.)

- [ ] **Step 5: Vertalingen toevoegen**

`lang/nl.json`:

```json
"> participant login": "> deelnemer-login",
"Log out": "Uitloggen",
":from until :until": ":from t/m :until",
"No active competition": "Geen actieve competitie",
"You are not linked to an active competition right now. Contact the organizer if you expected one.": "Je bent op dit moment niet gekoppeld aan een actieve competitie. Neem contact op met de organisator als je er wel een verwachtte."
```

- [ ] **Step 6: Checks + commit**

```bash
npm run check:fix && npm run types:check
php artisan test --compact tests/Feature/CompetitionAccessTest.php tests/Feature/Auth
```

Expected: geen lint-/type-errors, tests PASS.

```bash
git add -A && git commit -m "feat: mobile-first deelnemersgedeelte met competitie-login"
```

---

### Task 12: Eindcontrole en volledige suite

**Files:**

- Geen nieuwe bestanden; alleen verificatie en eventuele fixes.

- [ ] **Step 1: Wayfinder actueel**

Run: `php artisan wayfinder:generate`
Expected: geen diff, of alleen gegenereerde bestanden — meecommitten indien gewijzigd.

- [ ] **Step 2: Volledige checks**

```bash
composer ci:check
```

Expected: `npm run check` (0 warnings), `npm run types:check` (0 errors), pint-check schoon, PHPStan level 7 schoon, alle Pest-tests PASS. Fix gevonden issues en herhaal tot alles groen is.

- [ ] **Step 3: Handmatige rooktest (via Herd)**

Vraag Menno om (of doe zelf via de browser op `https://competition-tool.test`):

1. Als admin een competitie aan te maken en de deelnemerslijst te vullen (bestaand account, uitnodiging, direct aanmaken).
2. Als deelnemer in te loggen via `/{slug}/login` (mobiel formaat checken) en het dashboard te bekijken.
3. Een concept-competitie als deelnemer te bezoeken (verwacht 404).

- [ ] **Step 4: Afsluitende commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "chore: eindcontrole competitiebeheer en deelnemersgedeelte"
```

(Alleen committen als er daadwerkelijk wijzigingen zijn.) Daarna de superpowers:finishing-a-development-branch-skill volgen; niet pushen zonder expliciete toestemming.
