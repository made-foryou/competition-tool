<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the active locale and its ui translations are shared with inertia', function () {
    config(['app.locale' => 'nl']);
    app()->setLocale('nl');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'nl')
            ->where('translations.Log in', 'Inloggen'),
        );
});

test('an unknown locale falls back to an empty translation map', function () {
    app()->setLocale('de');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('translations', []),
        );
});
