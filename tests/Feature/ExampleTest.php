<?php

test('guests are redirected to login from home', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
