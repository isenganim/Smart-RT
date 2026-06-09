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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
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

    Schema::table('audit_logs', function ($table) {
        $table->dropColumn('metadata');
    });

    try {
        Volt::test('dashboard.letters.index')
            ->call('startUpdate', $letter->id)
            ->set('status', LetterStatus::DISETUJUI->value)
            ->set('notes', 'Silakan diambil.')
            ->call('saveUpdate');
    } catch (QueryException) {
        // The database failure is expected; the status update must still roll back.
    }

    expect($letter->fresh()->status)->toBe(LetterStatus::DIAJUKAN);
});

it('uses enum-backed validation for letter statuses', function () {
    $source = file_get_contents(resource_path('views/livewire/dashboard/letters/index.blade.php'));

    expect($source)
        ->toContain('Rule::enum(LetterStatus::class)')
        ->not->toContain('in:diajukan,disetujui,ditolak,selesai');
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

it('protects all sprint four dashboard routes', function (string $uri) {
    auth()->logout();
    $this->get($uri)->assertRedirect('/login');
})->with([
    '/dashboard/pengumuman',
    '/dashboard/laporan',
    '/dashboard/surat',
    '/dashboard/voting',
]);

it('keeps dashboard navigation available from the small breakpoint', function () {
    $source = file_get_contents(resource_path('views/components/layouts/app.blade.php'));

    expect($source)
        ->toContain('ring-white/10 sm:flex')
        ->not->toContain('ring-white/10 lg:flex');
});
