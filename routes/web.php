<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/manifest.webmanifest', fn () => response()->json(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript']));

Volt::route('/', 'portal.home')->name('portal.home');
Volt::route('/cek-nomor', 'portal.verify')->name('portal.verify');
Volt::route('/jadwal-ronda', 'portal.ronda')->name('portal.ronda');
Volt::route('/checkin-ronda', 'portal.checkin')->name('portal.checkin');

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');

    Volt::route('/dashboard/rumah', 'households.index')->name('households.index');
    Volt::route('/dashboard/rumah/{household}/qr', 'households.qr')->name('households.qr');
    Volt::route('/dashboard/warga', 'residents.index')->name('residents.index');
    Volt::route('/dashboard/ronda', 'dashboard.ronda.index')->name('ronda.index');
    Volt::route('/dashboard/ronda/{schedule}', 'dashboard.ronda.show')->name('ronda.show');
});
