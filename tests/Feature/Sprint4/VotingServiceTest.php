<?php

use App\Enums\VoteStatus;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use App\Models\Vote;
use App\Models\VoteOption;
use App\Services\VotingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
    $emptyOption = VoteOption::factory()->for($this->vote)->create();
    $result = $this->service->cast($this->vote, $this->option->id, '81234567890');
    $this->vote->update(['status' => VoteStatus::SELESAI]);
    $closed = $this->service->cast($this->vote->fresh(), $this->option->id, '81234567890');

    expect($result->success())->toBeTrue()
        ->and($closed->message)->toBe('Voting sudah ditutup.')
        ->and($this->service->tally($this->vote))->toEqualCanonicalizing([
            $this->option->id => 1,
            $emptyOption->id => 0,
        ]);
});

it('uses the resolved resident phone and audits successful ballots', function () {
    $result = $this->service->cast($this->vote, $this->option->id, '+62 812-3456-7890');

    expect($result->success())->toBeTrue()
        ->and($result->ballot->phone)->toBe($this->resident->phone)
        ->and(AuditLog::query()
            ->where('action', 'vote.cast')
            ->where('subject_type', 'vote_ballot')
            ->where('subject_id', $result->ballot->id)
            ->exists())->toBeTrue();
});

it('does not mask unrelated database errors as duplicate votes', function () {
    // The test suite is pinned to SQLite in-memory, so this trigger is intentionally SQLite-specific.
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_vote_ballot_insert
        BEFORE INSERT ON vote_ballots
        BEGIN
            SELECT RAISE(ABORT, 'simulated database failure');
        END
        SQL);

    try {
        expect(fn () => $this->service->cast($this->vote, $this->option->id, '81234567890'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_vote_ballot_insert');
    }
});

it('rolls back ballot creation when audit logging fails', function () {
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_vote_audit_insert
        BEFORE INSERT ON audit_logs
        WHEN NEW.action = 'vote.cast'
        BEGIN
            SELECT RAISE(ABORT, 'simulated audit failure');
        END
        SQL);

    try {
        expect(fn () => $this->service->cast($this->vote, $this->option->id, '81234567890'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_vote_audit_insert');
    }

    expect($this->vote->ballots()->count())->toBe(0);
});
