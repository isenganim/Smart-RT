<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{state, rules};

state(['email' => '', 'password' => '', 'remember' => false]);

rules([
    'email' => ['required', 'email'],
    'password' => ['required', 'string'],
]);

$login = function () {
    $this->validate();

    if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
        throw ValidationException::withMessages([
            'email' => 'Email atau password tidak sesuai.',
        ]);
    }

    request()->session()->regenerate();

    return $this->redirectRoute('dashboard', navigate: true);
};

?>

<x-layouts.auth title="Login Pengurus">
    <form wire:submit="login" class="space-y-5">
        <div>
            <label class="block text-sm font-semibold text-slate-700">Email</label>
            <input wire:model="email" type="email" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-inner shadow-slate-200/60 transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" placeholder="admin@smartrt.test" autofocus>
            @error('email') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700">Password</label>
            <input wire:model="password" type="password" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-inner shadow-slate-200/60 transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" placeholder="••••••••">
            @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-3 text-sm font-medium text-slate-600">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Ingat saya
        </label>

        <button class="w-full rounded-2xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/25 transition hover:-translate-y-0.5 hover:bg-emerald-400 focus:outline-none focus:ring-4 focus:ring-emerald-200">
            Masuk
        </button>

        <p class="text-center text-xs leading-5 text-slate-500">
            Portal ini hanya untuk pengurus. Hubungi admin RT jika lupa akses.
        </p>
    </form>
</x-layouts.auth>
