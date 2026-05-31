<?php

use App\Models\Household;

it('generates a unique qr token on create', function () {
    $a = Household::factory()->create();
    $b = Household::factory()->create();

    expect($a->qr_token)->not->toBeNull();
    expect($b->qr_token)->not->toBeNull();
    expect($a->qr_token)->not->toBe($b->qr_token);
});

it('does not overwrite an explicitly provided qr token', function () {
    $household = Household::factory()->create(['qr_token' => 'fixed-token-123']);

    expect($household->fresh()->qr_token)->toBe('fixed-token-123');
});

it('casts is_active to boolean and defaults active', function () {
    $household = Household::factory()->create();

    expect($household->is_active)->toBeTrue();
});
