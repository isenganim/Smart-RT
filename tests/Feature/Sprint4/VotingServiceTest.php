<?php

use App\Enums\VoteStatus;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Vote;
use App\Models\VoteOption;
use App\Services\VotingService;

beforeEach(function () {
    $this->service = app(VotingService::class);
    $this->vote = Vote::factory()->open()->create();
    $this->option = VoteOption::factory()->for($this->vote)->create();
    $this->resident = Resident::factory()->for(Household::factory())->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
});

it('validates voting eligibility and option ownership', function () {
    $otherOption = VoteOption::factory()->create();

    expect($this->service->cast($this->vote, $otherOption->id, '81234567890')->message)
        ->toBe('Pilihan tidak valid.')
        ->and($this->service->cast($this->vote, $this->option->id, '089900000000')->message)
        ->toContain('belum terdaftar');
});

it('rejects closed voting and tallies ballots', function () {
    $result = $this->service->cast($this->vote, $this->option->id, '81234567890');
    $this->vote->update(['status' => VoteStatus::SELESAI]);
    $closed = $this->service->cast($this->vote->fresh(), $this->option->id, '81234567890');

    expect($result->success())->toBeTrue()
        ->and($closed->message)->toBe('Voting sudah ditutup.')
        ->and($this->service->tally($this->vote)[$this->option->id])->toBe(1);
});
