<?php

namespace App\Http\Controllers;

use App\Services\KasReport;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class KasStatementExportController extends Controller
{
    public function __invoke(Request $request, KasReport $report)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $month = is_string($month) ? $month : now()->format('Y-m');

        $period = CarbonImmutable::canBeCreatedFromFormat($month, 'Y-m')
            ? CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $statement = $report->monthlyStatement((int) $period->year, (int) $period->month);

        Audit::record(auth()->user(), 'kas.statement_exported', null, null, [
            'month' => $period->format('Y-m'),
        ]);

        $pdf = Pdf::loadView('pdf.kas-monthly-statement', [
            'statement' => $statement,
            'period' => $period,
        ]);

        return $pdf->download('laporan-kas-'.$period->format('Y-m').'.pdf');
    }
}
