<?php

use App\Models\RondaScanSession;

it('generates a numeric pin on create', function () {
    $session = RondaScanSession::factory()->create(['pin' => null]);

    expect($session->pin)->toMatch('/^\d{4,6}$/');
});

it('does not overwrite an explicit pin', function () {
    $session = RondaScanSession::factory()->create(['pin' => '123456']);

    expect($session->fresh()->pin)->toBe('123456');
});

it('reports active when now is inside the window', function () {
    $session = RondaScanSession::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    expect($session->isActive())->toBeTrue();
});

it('reports expired when now is past the window', function () {
    $session = RondaScanSession::factory()->create([
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->subHour(),
    ]);

    expect($session->isActive())->toBeFalse();
});
