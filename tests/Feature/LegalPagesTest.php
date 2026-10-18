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

test('the privacy policy says what is kept about calls, and for how long', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('which accounts used it')
        ->assertSee('Usage statistics')
        ->assertSee('deleted a day after the call ends');
});
