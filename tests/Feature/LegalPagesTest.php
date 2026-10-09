<?php

test('the privacy policy is public', function () {
    config(['app.contact_email' => 'privacy@example.com']);

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('mailto:privacy@example.com', false);
});

test('the terms of service are public', function () {
    config(['app.contact_email' => 'privacy@example.com']);

    $this->get('/terms')
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('mailto:privacy@example.com', false);
});
