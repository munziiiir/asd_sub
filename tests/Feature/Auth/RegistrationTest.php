<?php

use Livewire\Volt\Volt;

use Illuminate\Support\Facades\Http;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    // Avoid external HIBP lookups during tests.
    Http::fake([
        'https://api.pwnedpasswords.com/*' => Http::response('', 404),
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'Passw0rd!')
        ->set('password_confirmation', 'Passw0rd!')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});
