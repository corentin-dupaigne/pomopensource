<?php

use App\Models\User;

test('a signed-in page gets a fresh token', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/session/refresh')
        ->assertOk()
        ->assertJson(['authenticated' => true])
        ->assertCookie('XSRF-TOKEN');
});

test('a page whose user was signed out learns it', function () {
    $this->getJson('/session/refresh')
        ->assertOk()
        ->assertJson(['authenticated' => false]);
});
