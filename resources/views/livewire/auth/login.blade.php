<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{state, rules, layout, title};

state(['email' => '', 'password' => '', 'remember' => false]);

layout('components.layouts.auth');
title('Login Pengurus');

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

<form wire:submit="login" class="space-y-5">
    <div>
        <label for="email" class="block text-sm font-medium text-ink-secondary">Email</label>
        <input wire:model="email" id="email" type="email" autocomplete="username" class="mt-2 w-full rounded-sm border border-hairline-input bg-canvas px-3 py-2.5 text-sm text-ink transition placeholder:text-ink-mute focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="admin@smartrt.test" autofocus>
        @error('email') <p class="mt-2 text-sm text-ruby">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-ink-secondary">Password</label>
        <input wire:model="password" id="password" type="password" autocomplete="current-password" class="mt-2 w-full rounded-sm border border-hairline-input bg-canvas px-3 py-2.5 text-sm text-ink transition placeholder:text-ink-mute focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="••••••••">
        @error('password') <p class="mt-2 text-sm text-ruby">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-center gap-2.5 text-sm text-ink-secondary">
        <input wire:model="remember" type="checkbox" class="rounded-xs border-hairline-input text-primary focus:ring-primary/30">
        Ingat saya
    </label>

    <x-admin.button type="submit" class="w-full">Masuk</x-admin.button>

    <p class="text-center text-xs leading-5 text-ink-mute">
        Portal ini hanya untuk pengurus. Hubungi admin RT jika lupa akses.
    </p>
</form>
