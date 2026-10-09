<?php

test('the privacy policy is public', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('identify')
        ->assertSee('activities.write');
});

test('the terms of service are public and link to the privacy policy', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee(route('privacy'));
});
