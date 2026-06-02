<?php

it('serves the warga portal home without authentication', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Portal Warga')
        ->assertSee('Cek Nomor HP');
});

it('does not require login for the portal home', function () {
    $this->get('/')->assertDontSee('Login Pengurus');
});

it('keeps the pengurus dashboard protected', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});
