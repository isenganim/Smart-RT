<?php

use Illuminate\Support\Facades\Auth;

$logout = function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return $this->redirectRoute('login', navigate: true);
};

?>

<button wire:click="logout" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 shadow-sm transition hover:bg-white/10 hover:text-white">
    Keluar
</button>
