<?php

use App\Enums\LetterStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\VoteStatus;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteOption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::ADMIN_RT]);
    $this->actingAs($this->admin);
});

it('creates and publishes announcements with audit logs', function () {
    Volt::test('dashboard.announcements.index')
        ->set('title', 'Kerja Bakti')
        ->set('body', 'Minggu pagi jam 7.')
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->firstOrFail();
    Volt::test('dashboard.announcements.index')->call('togglePublish', $announcement->id);

    expect($announcement->fresh()->is_published)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'announcement.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'announcement.published')->exists())->toBeTrue();
});

it('updates report and letter workflows with audit logs', function () {
    $report = Report::factory()->create();
    $letter = LetterRequest::factory()->create();

    Volt::test('dashboard.reports.index')
        ->call('startUpdate', $report->id)
        ->set('status', ReportStatus::SELESAI->value)
        ->set('notes', 'Sudah ditangani.')
        ->call('saveUpdate')
        ->assertHasNoErrors();

    Volt::test('dashboard.letters.index')
        ->call('startUpdate', $letter->id)
        ->set('status', LetterStatus::DISETUJUI->value)
        ->set('notes', 'Silakan diambil.')
        ->call('saveUpdate')
        ->assertHasNoErrors();

    expect($report->fresh()->status)->toBe(ReportStatus::SELESAI)
        ->and($letter->fresh()->status)->toBe(LetterStatus::DISETUJUI)
        ->and(AuditLog::query()->where('action', 'report.status_changed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'letter.status_changed')->exists())->toBeTrue();
});

it('rolls back a letter status update when audit logging fails', function () {
    $letter = LetterRequest::factory()->create();

    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_letter_audit_insert
        BEFORE INSERT ON audit_logs
        WHEN NEW.action = 'letter.status_changed'
        BEGIN
            SELECT RAISE(ABORT, 'simulated audit failure');
        END
        SQL);

    try {
        expect(fn () => Volt::test('dashboard.letters.index')
            ->call('startUpdate', $letter->id)
            ->set('status', LetterStatus::DISETUJUI->value)
            ->set('notes', 'Silakan diambil.')
            ->call('saveUpdate'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_letter_audit_insert');
    }

    expect($letter->fresh()->status)->toBe(LetterStatus::DIAJUKAN);
});

it('rolls back a report status update when audit logging fails', function () {
    $report = Report::factory()->create();

    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_report_audit_insert
        BEFORE INSERT ON audit_logs
        WHEN NEW.action = 'report.status_changed'
        BEGIN
            SELECT RAISE(ABORT, 'simulated audit failure');
        END
        SQL);

    try {
        expect(fn () => Volt::test('dashboard.reports.index')
            ->call('startUpdate', $report->id)
            ->set('status', ReportStatus::SELESAI->value)
            ->set('notes', 'Sudah ditangani.')
            ->call('saveUpdate'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_report_audit_insert');
    }

    expect($report->fresh()->status)->toBe(ReportStatus::BARU);
});

it('uses enum-backed validation for workflow statuses', function () {
    $reportSource = file_get_contents(resource_path('views/livewire/dashboard/reports/index.blade.php'));
    $source = file_get_contents(resource_path('views/livewire/dashboard/letters/index.blade.php'));

    expect($source)
        ->toContain('Rule::enum(LetterStatus::class)')
        ->not->toContain('in:diajukan,disetujui,ditolak,selesai')
        ->and($reportSource)
        ->toContain('Rule::enum(ReportStatus::class)')
        ->not->toContain('in:baru,diproses,selesai,ditolak');
});

it('creates activates and closes a vote', function () {
    Volt::test('dashboard.votes.index')
        ->set('question', 'Setuju kerja bakti bulanan?')
        ->set('optionsText', "Setuju\nTidak Setuju")
        ->set('starts_at', today()->toDateString())
        ->set('ends_at', today()->addDays(3)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $vote = Vote::query()->firstOrFail();
    expect($vote->options()->count())->toBe(2);

    Volt::test('dashboard.votes.index')->call('activate', $vote->id);
    expect($vote->fresh()->status)->toBe(VoteStatus::AKTIF);

    Volt::test('dashboard.votes.index')->call('close', $vote->id);
    expect($vote->fresh()->status)->toBe(VoteStatus::SELESAI)
        ->and(AuditLog::query()->where('action', 'vote.created')->exists())->toBeTrue();
});

it('rolls back vote creation when audit logging fails', function () {
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_vote_creation_audit_insert
        BEFORE INSERT ON audit_logs
        WHEN NEW.action = 'vote.created'
        BEGIN
            SELECT RAISE(ABORT, 'simulated audit failure');
        END
        SQL);

    try {
        expect(fn () => Volt::test('dashboard.votes.index')
            ->set('question', 'Voting yang gagal diaudit')
            ->set('optionsText', "Setuju\nTidak Setuju")
            ->call('save'))
            ->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS fail_vote_creation_audit_insert');
    }

    expect(Vote::query()->where('question', 'Voting yang gagal diaudit')->exists())->toBeFalse();
});

it('clears activation feedback after a vote is activated successfully', function () {
    $vote = Vote::factory()->create();

    $component = Volt::test('dashboard.votes.index')
        ->call('activate', $vote->id)
        ->assertHasErrors(['activation']);

    VoteOption::factory()->count(2)->for($vote)->create();

    $component
        ->call('activate', $vote->id)
        ->assertHasNoErrors(['activation']);

    expect($vote->fresh()->status)->toBe(VoteStatus::AKTIF);
});

it('protects all sprint four dashboard routes', function (string $uri) {
    auth()->logout();
    $this->get($uri)->assertRedirect('/login');
})->with([
    '/dashboard/pengumuman',
    '/dashboard/laporan',
    '/dashboard/surat',
    '/dashboard/voting',
]);

it('denies dashboard routes to users without a pengurus role', function (string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($uri)->assertForbidden();
})->with([
    '/dashboard/pengumuman',
    '/dashboard/laporan',
    '/dashboard/surat',
    '/dashboard/voting',
]);

it('keeps dashboard navigation available across desktop and mobile layouts', function () {
    $source = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

    expect($source)
        ->toContain('lg:grid-cols-[16rem_minmax(0,1fr)]')
        ->toContain('aria-label="Navigasi utama"')
        ->toContain('lg:hidden')
        ->toContain('Lainnya');
});
