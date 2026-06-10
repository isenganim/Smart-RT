<?php

use App\Enums\LetterStatus;
use App\Enums\ReportStatus;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use App\Models\RondaAssignment;
use App\Models\RondaSchedule;
use App\Models\User;
use App\Services\AdminDashboardSummary;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-06-11 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('builds truthful dashboard metrics actions trend and activity', function () {
    $actor = User::factory()->create(['name' => 'Ketua RT']);
    $paid = Household::factory()->create(['is_active' => true]);
    $unpaid = Household::factory()->create(['is_active' => true]);
    Household::factory()->inactive()->create();
    $activeResident = Resident::factory()->for($paid)->create(['is_active' => true]);
    Resident::factory()->for($unpaid)->create(['is_active' => false]);

    CashTransaction::factory()->for($paid)->create([
        'date' => today(),
        'type' => TransactionType::IURAN_HARIAN,
        'amount' => 500,
    ]);
    CashTransaction::factory()->for($paid)->create([
        'date' => today()->subDay(),
        'type' => TransactionType::DENDA,
        'amount' => 5000,
    ]);
    CashTransaction::factory()->for($paid)->create([
        'date' => today(),
        'amount' => 9000,
        'cancelled_at' => now(),
        'reason' => 'Salah input',
    ]);

    Report::factory()->create(['status' => ReportStatus::BARU]);
    Report::factory()->create(['status' => ReportStatus::SELESAI]);
    LetterRequest::factory()->create(['status' => LetterStatus::DIAJUKAN]);
    LetterRequest::factory()->create(['status' => LetterStatus::SELESAI]);

    $schedule = RondaSchedule::factory()->create(['date' => today()]);
    RondaAssignment::factory()
        ->for($schedule)
        ->for($activeResident)
        ->create(['checked_in_at' => null]);

    AuditLog::query()->create([
        'actor_id' => $actor->id,
        'action' => 'household.created',
        'subject_type' => 'household',
        'subject_id' => $paid->id,
        'metadata' => [],
    ]);

    $summary = app(AdminDashboardSummary::class)->forDate(today());

    expect($summary['metrics'])->toMatchArray([
        'households' => 2,
        'residents' => 1,
        'month_cash' => 5500,
        'action_count' => 4,
    ]);
    expect(collect($summary['actions'])->pluck('count', 'key')->all())
        ->toMatchArray([
            'unpaid_households' => 1,
            'open_reports' => 1,
            'pending_letters' => 1,
            'missing_checkins' => 1,
        ]);
    expect($summary['cash_trend'])->toHaveCount(30);
    expect(collect($summary['cash_trend'])->last()['total'])->toBe(500);
    expect($summary['recent_activity'])->first()->toMatchArray([
        'label' => 'Rumah/KK ditambahkan',
        'actor' => 'Ketua RT',
    ]);
});
