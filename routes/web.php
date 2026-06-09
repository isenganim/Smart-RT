<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/manifest.webmanifest', fn () => response()->json(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript']));

Volt::route('/', 'portal.home')->name('portal.home');
Volt::route('/cek-nomor', 'portal.verify')->name('portal.verify');
Volt::route('/jadwal-ronda', 'portal.ronda')->name('portal.ronda');
Volt::route('/checkin-ronda', 'portal.checkin')->name('portal.checkin');
Volt::route('/scan-iuran', 'portal.scan')->name('portal.scan');
Volt::route('/pengumuman', 'portal.announcements')->name('portal.announcements');
Volt::route('/lapor', 'portal.report')->name('portal.report');
Volt::route('/surat', 'portal.letter')->name('portal.letter');
Volt::route('/voting', 'portal.votes')->name('portal.votes');
Volt::route('/voting/{vote}', 'portal.vote')->name('portal.vote');

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');

    Volt::route('/dashboard/rumah', 'households.index')->name('households.index');
    Volt::route('/dashboard/rumah/{household}/qr', 'households.qr')->name('households.qr');
    Volt::route('/dashboard/warga', 'residents.index')->name('residents.index');
    Volt::route('/dashboard/ronda', 'dashboard.ronda.index')->name('ronda.index');
    Volt::route('/dashboard/ronda/{schedule}', 'dashboard.ronda.show')->name('ronda.show');
    Volt::route('/dashboard/sesi-scan', 'dashboard.scan.index')->name('scan-sessions.index');
    Volt::route('/dashboard/denda', 'dashboard.denda.index')->name('denda.index');
    Volt::route('/dashboard/kas', 'dashboard.kas.index')->name('kas.index');
    Volt::route('/dashboard/kas/transaksi', 'dashboard.kas.transactions')->name('kas.transactions');
    Volt::route('/dashboard/pengumuman', 'dashboard.announcements.index')->name('announcements.index');
    Volt::route('/dashboard/laporan', 'dashboard.reports.index')->name('reports.index');
    Volt::route('/dashboard/surat', 'dashboard.letters.index')->name('letters.index');
    Volt::route('/dashboard/voting', 'dashboard.votes.index')->name('votes.index');
    Volt::route('/dashboard/voting/{vote}', 'dashboard.votes.show')->name('votes.show');
});
