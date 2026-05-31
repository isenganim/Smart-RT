<?php

use Illuminate\Support\Facades\Auth;

$logout = function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return $this->redirectRoute('login', navigate: true);
};

?>

<button wire:click="logout" class="text-sm font-medium text-slate-600 hover:text-slate-900">
    Keluar
</button>
