<?php

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\ReportStatus;
use App\Enums\VoteStatus;
use App\Models\Announcement;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use Illuminate\Database\QueryException;

it('prevents duplicate option labels within the same vote', function () {
    $vote = Vote::factory()->create();
    VoteOption::factory()->for($vote)->create(['label' => 'Setuju']);

    expect(fn () => VoteOption::factory()->for($vote)->create(['label' => 'Setuju']))
        ->toThrow(QueryException::class);
});

it('supports announcement publication queries', function () {
    $older = Announcement::factory()->published()->create(['published_at' => now()->subDays(2)]);
    $newer = Announcement::factory()->published()->create(['published_at' => now()->subDay()]);
    Announcement::factory()->create();

    $announcements = Announcement::query()->published()->get();

    expect($announcements)->toHaveCount(2)
        ->and($announcements->first()->is($newer))->toBeTrue()
        ->and($announcements->last()->is($older))->toBeTrue();
});

it('casts and scopes reports and letter requests', function () {
    $report = Report::factory()->create(['phone' => '0812-3456-7890', 'status' => ReportStatus::BARU]);
    Report::factory()->create(['status' => ReportStatus::SELESAI]);
    $letter = LetterRequest::factory()->create([
        'phone' => '0813-4567-8901',
        'type' => LetterType::DOMISILI,
        'status' => LetterStatus::DIAJUKAN,
    ]);
    LetterRequest::factory()->create(['status' => LetterStatus::SELESAI]);

    expect($report->fresh()->phone)->toBe('81234567890')
        ->and($report->fresh()->status)->toBe(ReportStatus::BARU)
        ->and(Report::query()->open()->count())->toBe(1)
        ->and($letter->fresh()->phone)->toBe('81345678901')
        ->and($letter->fresh()->type)->toBe(LetterType::DOMISILI)
        ->and(LetterRequest::query()->pending()->count())->toBe(1);
});

it('models voting periods and options', function () {
    $creator = User::factory()->create();
    $resident = Resident::factory()->create();
    $vote = Vote::factory()->create([
        'created_by' => $creator->id,
        'status' => VoteStatus::AKTIF,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
    $option = VoteOption::factory()->for($vote)->create();
    VoteOption::factory()->for($vote)->create();
    $ballot = VoteBallot::query()->create([
        'vote_id' => $vote->id,
        'vote_option_id' => $option->id,
        'resident_id' => $resident->id,
        'phone' => $resident->phone,
    ]);

    expect($vote->fresh()->status)->toBe(VoteStatus::AKTIF)
        ->and($vote->isOpen())->toBeTrue()
        ->and($vote->options)->toHaveCount(2)
        ->and($vote->creator->is($creator))->toBeTrue()
        ->and($ballot->resident->is($resident))->toBeTrue();
});
