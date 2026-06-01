<?php

it('serves web manifest', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Smart RT');
});

it('serves service worker', function () {
    $this->get('/sw.js')
        ->assertOk()
        ->assertSee('smart-rt-cache');
});
