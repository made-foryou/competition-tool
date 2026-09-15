<?php

use App\Enums\UserRole;
use App\Models\Competition;
use App\Models\Invitation;
use App\Models\MatchDay;
use App\Models\MatchDayAvailability;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());
});

test('an existing user is linked directly with link mode', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
        'mode' => 'link',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant linked. The match list has been updated.')]);

    expect($competition->participants()->whereKey($user->id)->exists())->toBeTrue();
});

test('linking an existing admin keeps the admin role', function () {
    $competition = Competition::factory()->create();
    $admin = User::factory()->withTwoFactor()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $admin->email,
        'mode' => 'link',
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
        'mode' => 'link',
    ])->assertRedirect();

    expect($competition->participants()->count())->toBe(1);
});

test('an unknown email with invite mode sends a participant invitation', function () {
    Notification::fake();
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'nieuw@example.com',
        'mode' => 'invite',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

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
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant added. The match list has been updated.')]);

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
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Participant removed. The match list has been updated.')]);

    expect($competition->participants()->count())->toBe(0)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

test('detaching a participant removes their availability for that competition', function () {
    $competition = Competition::factory()->create();
    $matchDay = MatchDay::factory()->create(['competition_id' => $competition]);
    $other = Competition::factory()->create();
    $otherMatchDay = MatchDay::factory()->create(['competition_id' => $other]);

    $user = User::factory()->participant()->create();
    $competition->participants()->attach($user);
    $other->participants()->attach($user);

    MatchDayAvailability::create(['user_id' => $user->id, 'match_day_id' => $matchDay->id]);
    MatchDayAvailability::create(['user_id' => $user->id, 'match_day_id' => $otherMatchDay->id]);

    $this->delete(route('competitions.participants.destroy', [$competition, $user]))
        ->assertRedirect();

    expect($user->matchDayAvailabilities()->pluck('match_day_id')->all())->toBe([$otherMatchDay->id]);
});

test('participants cannot manage the participant list', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->post(route('competitions.participants.store', $competition), [
            'email' => 'x@example.com',
            'mode' => 'invite',
        ])->assertForbidden();
});

test('an existing account with invite mode is invited instead of linked', function () {
    Notification::fake();

    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
        'mode' => 'invite',
    ])->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

    // De deelnemer beslist zelf: koppelen gebeurt pas als hij zich aanmeldt.
    expect($competition->participants()->count())->toBe(0)
        ->and($competition->invitations()->pending()->count())->toBe(1);

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification): bool => $notification->hasAccount,
    );
});

test('linking an existing account settles their pending invitation', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();
    Invitation::factory()->create([
        'competition_id' => $competition->id,
        'email' => $user->email,
    ]);

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
        'mode' => 'link',
    ])->assertRedirect();

    expect($competition->invitations()->pending()->count())->toBe(0);
});

test('create mode is rejected for an email that already has an account', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => $user->email,
        'mode' => 'create',
        'name' => 'Dubbel Account',
        'password' => 'SuperSecret123!',
    ])->assertSessionHasErrors('mode');

    expect($competition->participants()->count())->toBe(0);
});

test('link mode is rejected for an unknown email address', function () {
    $competition = Competition::factory()->create();

    $this->post(route('competitions.participants.store', $competition), [
        'email' => 'onbekend@example.com',
        'mode' => 'link',
    ])->assertSessionHasErrors('mode');

    expect($competition->participants()->count())->toBe(0);
});

test('the lookup tells whether an email already has an account', function () {
    $competition = Competition::factory()->create();
    $user = User::factory()->participant()->create(['nickname' => 'Sanne']);

    $this->getJson(route('competitions.participants.lookup', [$competition, 'email' => $user->email]))
        ->assertOk()
        ->assertJson(['exists' => true, 'name' => 'Sanne']);

    $this->getJson(route('competitions.participants.lookup', [$competition, 'email' => 'onbekend@example.com']))
        ->assertOk()
        ->assertJson(['exists' => false, 'name' => null]);
});

test('participants cannot use the lookup', function () {
    $competition = Competition::factory()->create();

    $this->actingAs(User::factory()->participant()->create())
        ->getJson(route('competitions.participants.lookup', [$competition, 'email' => 'iemand@example.com']))
        ->assertForbidden();
});
