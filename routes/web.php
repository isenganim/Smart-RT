<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/manifest.webmanifest', fn () => response()->json(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript']));

Volt::route('/', 'portal.home')->name('portal.home');
Volt::route('/cek-nomor', 'portal.verify')->name('portal.verify');

Volt::route('/jadwal-ronda', 'portal.ronda')->middleware('feature:ronda')->name('portal.ronda');
Volt::route('/pengumuman', 'portal.announcements')->middleware('feature:announcements')->name('portal.announcements');

Route::middleware('registered_phone')->group(function () {
    Volt::route('/checkin-ronda', 'portal.checkin')->middleware('feature:ronda')->name('portal.checkin');
    Volt::route('/scan-iuran', 'portal.scan')->middleware('feature:kas')->name('portal.scan');
    Volt::route('/lapor', 'portal.report')->middleware('feature:reports')->name('portal.report');
    Volt::route('/surat', 'portal.letter')->middleware('feature:letters')->name('portal.letter');
    Volt::route('/voting', 'portal.votes')->middleware('feature:voting')->name('portal.votes');
    Volt::route('/voting/{vote}', 'portal.vote')->middleware('feature:voting')->name('portal.vote');
});

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');

    Volt::route('/dashboard/rumah', 'households.index')->name('households.index');
    Volt::route('/dashboard/rumah/{household}/qr', 'households.qr')->name('households.qr');
    Volt::route('/dashboard/warga', 'residents.index')->name('residents.index');

    Volt::route('/dashboard/pengaturan', 'dashboard.settings.index')->name('settings.index');

    Route::middleware('feature:ronda')->group(function () {
        Volt::route('/dashboard/ronda', 'dashboard.ronda.index')->name('ronda.index');
        Volt::route('/dashboard/ronda/{schedule}', 'dashboard.ronda.show')->name('ronda.show');
        Volt::route('/dashboard/sesi-scan', 'dashboard.scan.index')->name('scan-sessions.index');
        Volt::route('/dashboard/denda', 'dashboard.denda.index')->name('denda.index');
    });

    Route::middleware('feature:kas')->group(function () {
        Volt::route('/dashboard/kas', 'dashboard.kas.index')->name('kas.index');
        Volt::route('/dashboard/kas/transaksi', 'dashboard.kas.transactions')->name('kas.transactions');
        Volt::route('/dashboard/kas/laporan-bulanan', 'dashboard.kas.statement')->name('kas.statement');
        Route::get('/dashboard/kas/laporan-bulanan/export', \App\Http\Controllers\KasStatementExportController::class)->name('kas.statement.export');
    });

    Route::middleware('feature:announcements')->group(function () {
        Volt::route('/dashboard/pengumuman', 'dashboard.announcements.index')->name('announcements.index');
    });

    Route::middleware('feature:reports')->group(function () {
        Volt::route('/dashboard/laporan', 'dashboard.reports.index')->name('reports.index');
    });

    Route::middleware('feature:letters')->group(function () {
        Volt::route('/dashboard/surat', 'dashboard.letters.index')->name('letters.index');
    });

    Route::middleware('feature:voting')->group(function () {
        Volt::route('/dashboard/voting', 'dashboard.votes.index')->name('votes.index');
        Volt::route('/dashboard/voting/{vote}', 'dashboard.votes.show')->name('votes.show');
    });

    Route::middleware('feature:inventory')->group(function () {
        Volt::route('/dashboard/inventaris', 'dashboard.inventory.index')->name('inventory.index');
    });
});
