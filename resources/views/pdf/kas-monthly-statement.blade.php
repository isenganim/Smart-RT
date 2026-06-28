<?php
    $rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan {{ $period->translatedFormat('F Y') }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 12px; margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #666; }
        .header { border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 6px 8px; text-align: left; }
        td.num, th.num { text-align: right; }
        .section-title { font-size: 13px; font-weight: bold; margin-top: 16px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .row { border-bottom: 1px solid #eee; }
        .total-row td { border-top: 1px solid #999; font-weight: bold; }
        .grand { background: #f3f3f3; }
        .grand td { font-size: 14px; font-weight: bold; padding: 10px 8px; }
        .neg { color: #b00020; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Keuangan RT</h1>
        <p class="muted">Periode: {{ $period->translatedFormat('F Y') }}</p>
    </div>

    <table>
        <tr class="row">
            <td>Saldo Awal</td>
            <td class="num">{{ $rupiah($statement['opening_balance']) }}</td>
        </tr>
    </table>

    <div class="section-title">Pemasukan</div>
    <table>
        <tr class="row"><td>Iuran Harian</td><td class="num">{{ $rupiah($statement['income']['iuran']) }}</td></tr>
        <tr class="row"><td>Denda Ronda</td><td class="num">{{ $rupiah($statement['income']['denda']) }}</td></tr>
        @if ($statement['income']['koreksi'] !== 0)
            <tr class="row"><td>Koreksi</td><td class="num">{{ $rupiah($statement['income']['koreksi']) }}</td></tr>
        @endif
        <tr class="total-row"><td>Total Pemasukan</td><td class="num">{{ $rupiah($statement['income']['total_in']) }}</td></tr>
    </table>

    <div class="section-title">Pengeluaran</div>
    <table>
        @forelse ($statement['expenses'] as $expense)
            <tr class="row"><td>{{ $expense['category'] }}</td><td class="num neg">-{{ $rupiah($expense['amount']) }}</td></tr>
        @empty
            <tr class="row"><td class="muted" colspan="2">Tidak ada pengeluaran pada periode ini.</td></tr>
        @endforelse
        <tr class="total-row"><td>Total Pengeluaran</td><td class="num neg">-{{ $rupiah($statement['total_out']) }}</td></tr>
    </table>

    <table>
        <tr class="grand">
            <td>Saldo Akhir</td>
            <td class="num">{{ $rupiah($statement['closing_balance']) }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 24px; font-size: 10px;">
        Dicetak {{ now()->translatedFormat('d F Y H:i') }} · Smart RT
    </p>
</body>
</html>
