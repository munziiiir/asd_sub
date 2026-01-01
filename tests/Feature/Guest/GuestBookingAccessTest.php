<?php

use App\Models\User;

test('booking start requires authentication', function () {
    $this->get(route('booking.start'))->assertRedirect(route('login'));
});

test('authenticated guest can view booking start page', function () {
    $user = $this->createGuestUser();

    $this->actingAs($user)
        ->get(route('booking.start'))
        ->assertStatus(200);
});
