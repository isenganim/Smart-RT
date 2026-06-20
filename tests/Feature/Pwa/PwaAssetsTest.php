<?php

it('serves web manifest', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('name', 'Smart RT');
});

it('uses the indigo brand color as theme color', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('theme_color', '#533afd');
});

it('declares installable icons', function () {
    $icons = $this->get('/manifest.webmanifest')->assertOk()->json('icons');

    expect($icons)
        ->not->toBeEmpty()
        ->and(collect($icons)->pluck('sizes')->unique()->toArray())
        ->toContain('192x192')
        ->and(collect($icons)->pluck('sizes')->unique()->toArray())
        ->toContain('512x512')
        ->and(collect($icons)->where('purpose', 'maskable'))->not->toBeEmpty();
});

it('serves each declared icon file', function () {
    $icons = $this->get('/manifest.webmanifest')->assertOk()->json('icons');

    foreach (collect($icons)->pluck('src') as $src) {
        $path = public_path(ltrim($src, '/'));
        expect(file_exists($path))->toBeTrue("Missing icon asset: {$src}");
    }
});

it('serves service worker', function () {
    $this->get('/sw.js')
        ->assertOk()
        ->assertSee('smart-rt-cache');
});
