<?php

test('the application entry point redirects to the dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('dashboard'));
});
