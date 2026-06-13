<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;

it('normalizes phone numbers to a canonical form', function () {
    expect(PhoneNumber::normalize('0812-3456-7890'))->toBe('81234567890');
    expect(PhoneNumber::normalize('+62 812 3456 7890'))->toBe('81234567890');
});

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->for($household)->create();

    expect($resident->household->is($household))->toBeTrue();
});

it('rejects a duplicate phone for active residents', function () {
    $household = Household::factory()->create();
    Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => true]);

    $validator = Validator::make(
        ['phone' => '0812-3456-7890'],
        ['phone' => [new UniqueActivePhone]],
    );

    expect($validator->fails())->toBeTrue();
});

it('allows reusing a phone held only by an inactive resident', function () {
    $household = Household::factory()->create();
    Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => false]);

    $validator = Validator::make(
        ['phone' => '81234567890'],
        ['phone' => [new UniqueActivePhone]],
    );

    expect($validator->fails())->toBeFalse();
});

it('ignores a given resident id when updating', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->for($household)->create(['phone' => '81234567890', 'is_active' => true]);

    $validator = Validator::make(
        ['phone' => '81234567890'],
        ['phone' => [new UniqueActivePhone(ignoreResidentId: $resident->id)]],
    );

    expect($validator->fails())->toBeFalse();
});
