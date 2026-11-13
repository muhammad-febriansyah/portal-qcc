<?php

use Inertia\Testing\AssertableInertia as Assert;

test('self registration is hidden and unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('canRegister', false)
            ->where('canResetPassword', true));
});
