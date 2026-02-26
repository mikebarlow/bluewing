<?php

test('index redirects to dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('dashboard'));
});
