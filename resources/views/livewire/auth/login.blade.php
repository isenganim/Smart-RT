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
    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input wire:model="email" type="email" class="mt-1 w-full rounded-lg border-slate-300" autofocus>
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input wire:model="password" type="password" class="mt-1 w-full rounded-lg border-slate-300">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-300">
            Ingat saya
        </label>

        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
            Masuk
        </button>
    </form>
</x-layouts.auth>
