<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Household;
use App\Models\LetterRequest;
use App\Models\Report;
use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardSummary
{
    public function __construct(private readonly KasReport $kasReport) {}

    public function forDate(CarbonInterface $date): array
    {
        $reference = Carbon::instance($date)->startOfDay();
        $actions = $this->actions($reference);

        return [
            'metrics' => [
                'households' => Household::query()->where('is_active', true)->count(),
                'residents' => Resident::query()->where('is_active', true)->count(),
                'month_cash' => $this->kasReport->rangeTotal(
                    $reference->copy()->startOfMonth(),
                    $reference->copy()->endOfMonth(),
                ),
                'action_count' => collect($actions)->sum('count'),
            ],
            'actions' => $actions,
            'cash_trend' => $this->cashTrend($reference),
            'recent_activity' => $this->recentActivity(),
        ];
    }

    private function actions(CarbonInterface $date): array
    {
        return [
            [
                'key' => 'unpaid_households',
                'label' => 'Rumah belum membayar iuran',
                'description' => $date->translatedFormat('d F Y'),
                'count' => $this->kasReport->unpaidHouseholds($date)->count(),
                'tone' => 'danger',
                'route' => 'kas.index',
                'query' => ['date' => $date->toDateString()],
            ],
            [
                'key' => 'open_reports',
                'label' => 'Laporan warga perlu ditangani',
                'description' => 'Status baru atau sedang diproses',
                'count' => Report::query()->open()->count(),
                'tone' => 'warning',
                'route' => 'reports.index',
                'query' => ['filter' => 'open'],
            ],
            [
                'key' => 'pending_letters',
                'label' => 'Permohonan surat perlu diproses',
                'description' => 'Diajukan atau sudah disetujui',
                'count' => LetterRequest::query()->pending()->count(),
                'tone' => 'warning',
                'route' => 'letters.index',
                'query' => ['filter' => 'pending'],
            ],
            [
                'key' => 'missing_checkins',
                'label' => 'Petugas ronda belum check-in',
                'description' => $date->translatedFormat('d F Y'),
                'count' => $this->kasReport->missingCheckins($date)->count(),
                'tone' => 'info',
                'route' => 'ronda.index',
                'query' => [],
            ],
        ];
    }

    private function cashTrend(CarbonInterface $date): Collection
    {
        $from = $date->copy()->subDays(29);
        $totals = CashTransaction::query()
            ->active()
            ->whereBetween('date', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $date->copy()->endOfDay()->toDateTimeString(),
            ])
            ->selectRaw('DATE(date) as day, SUM(amount) as total')
            ->groupByRaw('DATE(date)')
            ->pluck('total', 'day');

        return collect(range(0, 29))->map(function (int $offset) use ($from, $totals) {
            $day = $from->copy()->addDays($offset);

            return [
                'date' => $day->toDateString(),
                'label' => $day->format('d M'),
                'total' => (int) ($totals[$day->toDateString()] ?? 0),
            ];
        });
    }

    private function recentActivity(): Collection
    {
        return AuditLog::query()
            ->with('actor')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'label' => $this->activityLabel($log->action),
                'actor' => $log->actor?->name ?? 'Sistem',
                'time' => $log->created_at,
            ]);
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'household.created' => 'Rumah/KK ditambahkan',
            'resident.created' => 'Warga ditambahkan',
            'kas.iuran.created' => 'Iuran dicatat',
            'kas.iuran.scanned' => 'Iuran dipindai',
            'kas.denda.created' => 'Denda dicatat',
            'kas.transaction.cancelled' => 'Transaksi kas dibatalkan',
            'ronda.schedule.created' => 'Jadwal ronda dibuat',
            'ronda.assignment.added' => 'Petugas ronda ditambahkan',
            'ronda.assignment.checked_in' => 'Petugas ronda check-in',
            'report.status_changed' => 'Status laporan diperbarui',
            'letter.status_changed' => 'Status surat diperbarui',
            'announcement.published' => 'Pengumuman diterbitkan',
            default => str($action)->replace('.', ' ')->headline()->toString(),
        };
    }
}
