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

it('renders the announcement management page before a publication action is selected', function () {
    Announcement::factory()->create(['title' => 'Kerja Bakti']);

    $this->get('/dashboard/pengumuman')
        ->assertOk()
        ->assertSee('Kerja Bakti');
});

it('creates and publishes announcements with audit logs', function () {
    Volt::test('dashboard.announcements.index')
        ->set('title', 'Kerja Bakti')
        ->set('body', 'Minggu pagi jam 7.')
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->firstOrFail();
    Volt::test('dashboard.announcements.index')
        ->call('startToggle', $announcement->id)
        ->call('confirmToggle');

    expect($announcement->fresh()->is_published)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'announcement.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'announcement.published')->exists())->toBeTrue();
});

it('sanitizes rich announcement content before saving', function () {
    Volt::test('dashboard.announcements.index')
        ->set('title', 'Kerja Bakti')
        ->set('body', '<div><strong>Minggu pagi</strong></div><script>alert(1)</script><a href="javascript:alert(1)">Detail</a>')
        ->call('save')
        ->assertHasNoErrors();

    $body = Announcement::query()->firstOrFail()->body;

    expect($body)
        ->toContain('<strong>Minggu pagi</strong>')
        ->not->toContain('<script')
        ->not->toContain('javascript:');
});

it('uses a rich text editor and explicit announcement publication controls', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/announcements/index.blade.php'));

    expect($source)
        ->toContain('<trix-editor')
        ->toContain('data-announcement-editor')
        ->toContain('<x-admin.page-header')
        ->toContain('<x-admin.status-badge')
        ->toContain('Tampilkan')
        ->toContain('Sembunyikan')
        ->toContain("\$this->dispatch('announcement-edit-started')")
        ->toContain('@announcement-edit-started.window')
        ->toContain("scrollIntoView({ behavior: 'smooth'")
        ->toContain('$refs.title.focus()')
        ->toContain('x-ref="title"')
        ->not->toContain('<textarea wire:model="body"');
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

it('edits an existing draft vote and its options', function () {
    $vote = Vote::factory()->create(['question' => 'Original Question']);
    VoteOption::factory()->for($vote)->create(['label' => 'Original Option']);

    Volt::test('dashboard.votes.index')
        ->call('edit', $vote->id)
        ->assertSet('question', 'Original Question')
        ->assertSet('optionsText', 'Original Option')
        ->set('question', 'Updated Question')
        ->set('optionsText', "Updated Option 1\nUpdated Option 2")
        ->call('save')
        ->assertHasNoErrors();

    $vote = $vote->fresh();
    expect($vote->question)->toBe('Updated Question')
        ->and($vote->options()->count())->toBe(2)
        ->and($vote->options()->pluck('label')->toArray())->toBe(['Updated Option 1', 'Updated Option 2'])
        ->and(AuditLog::query()->where('action', 'vote.updated')->exists())->toBeTrue();
});

it('locks and rechecks a draft vote inside the update transaction', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/votes/index.blade.php'));

    expect($source)
        ->toMatch("/Vote::query\\(\\)\\s*->lockForUpdate\\(\\)\\s*->withCount\\('ballots'\\)\\s*->findOrFail/");
});

it('does not reactivate a completed vote', function () {
    $vote = Vote::factory()->create(['status' => VoteStatus::SELESAI]);
    VoteOption::factory()->count(2)->for($vote)->create();

    Volt::test('dashboard.votes.index')
        ->call('activate', $vote->id)
        ->assertHasErrors(['activation']);

    expect($vote->fresh()->status)->toBe(VoteStatus::SELESAI);
});

it('does not close a draft vote', function () {
    $vote = Vote::factory()->create(['status' => VoteStatus::DRAFT]);

    Volt::test('dashboard.votes.index')
        ->call('close', $vote->id)
        ->assertHasErrors(['activation']);

    expect($vote->fresh()->status)->toBe(VoteStatus::DRAFT);
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
