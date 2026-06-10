<?php

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('portal-report:127.0.0.1');
    RateLimiter::clear('portal-letter:127.0.0.1');
    RateLimiter::clear('portal-vote:127.0.0.1');
    $this->household = Household::factory()->create();
    $this->resident = Resident::factory()->for($this->household)->create([
        'phone' => '81234567890',
        'is_active' => true,
    ]);
});

it('shows only published announcements publicly', function () {
    Announcement::factory()->published()->create(['title' => 'Kerja Bakti']);
    Announcement::factory()->create(['title' => 'Draft Rahasia']);

    $this->get('/pengumuman')
        ->assertOk()
        ->assertSee('Kerja Bakti')
        ->assertDontSee('Draft Rahasia');
});

it('accepts reports and letters only from registered active phones', function () {
    Volt::test('portal.report')
        ->set('phone', '0812-3456-7890')
        ->set('category', 'Keamanan')
        ->set('description', 'Lampu jalan mati.')
        ->call('submit')
        ->assertSee('Laporan terkirim');

    Volt::test('portal.letter')
        ->set('phone', '0812-3456-7890')
        ->set('type', 'domisili')
        ->set('purpose', 'Administrasi bank.')
        ->call('submit')
        ->assertSee('Pengajuan terkirim');

    Volt::test('portal.report')
        ->set('phone', '089900000000')
        ->set('category', 'Keamanan')
        ->set('description', 'Laporan tidak valid.')
        ->call('submit')
        ->assertSee('belum terdaftar');

    expect(Report::query()->count())->toBe(1)
        ->and(Report::query()->firstOrFail()->phone)->toBe($this->resident->phone)
        ->and(LetterRequest::query()->count())->toBe(1)
        ->and(LetterRequest::query()->firstOrFail()->phone)->toBe($this->resident->phone)
        ->and(AuditLog::query()->where('action', 'report.submitted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'letter.submitted')->exists())->toBeTrue();
});

it('allows one vote per registered phone', function () {
    $vote = Vote::factory()->open()->create();
    $option = VoteOption::factory()->for($vote)->create(['label' => 'Setuju']);

    $component = Volt::test('portal.vote', ['vote' => $vote])
        ->set('phone', '0812-3456-7890')
        ->set('optionId', $option->id)
        ->call('submit')
        ->assertSee('Suara Anda tercatat');

    $component->set('phone', '081234567890')
        ->set('optionId', $option->id)
        ->call('submit')
        ->assertSee('sudah memberikan suara');

    expect(VoteBallot::query()->count())->toBe(1);
});

it('does not expose draft votes publicly', function () {
    $vote = Vote::factory()->create();
    VoteOption::factory()->for($vote)->create();

    $this->get(route('portal.vote', $vote))->assertNotFound();
});

it('scopes vote throttling to each poll', function () {
    $firstVote = Vote::factory()->open()->create();
    $secondVote = Vote::factory()->open()->create();
    $secondOption = VoteOption::factory()->for($secondVote)->create();

    RateLimiter::clear('portal-vote:'.$secondVote->id.':127.0.0.1');
    RateLimiter::hit('portal-vote:127.0.0.1', 60);
    RateLimiter::hit('portal-vote:127.0.0.1', 60);
    RateLimiter::hit('portal-vote:127.0.0.1', 60);
    RateLimiter::hit('portal-vote:127.0.0.1', 60);
    RateLimiter::hit('portal-vote:127.0.0.1', 60);
    RateLimiter::hit('portal-vote:'.$firstVote->id.':127.0.0.1', 60);
    RateLimiter::hit('portal-vote:'.$firstVote->id.':127.0.0.1', 60);
    RateLimiter::hit('portal-vote:'.$firstVote->id.':127.0.0.1', 60);
    RateLimiter::hit('portal-vote:'.$firstVote->id.':127.0.0.1', 60);
    RateLimiter::hit('portal-vote:'.$firstVote->id.':127.0.0.1', 60);

    Volt::test('portal.vote', ['vote' => $secondVote])
        ->set('phone', '0812-3456-7890')
        ->set('optionId', $secondOption->id)
        ->call('submit')
        ->assertSee('Suara Anda tercatat');
});
