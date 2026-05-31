<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/manifest.webmanifest', fn () => response()->json(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript']));

Route::redirect('/', '/dashboard');

Volt::route('/login', 'auth.login')->name('login');

Route::middleware(['auth', 'pengurus'])->group(function () {
    Volt::route('/dashboard', 'dashboard.index')->name('dashboard');
});
