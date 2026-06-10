<?php

use Illuminate\Support\Facades\Auth;

$logout = function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return $this->redirectRoute('login', navigate: true);
};

?>

<x-admin.button
    variant="ghost"
    wire:click="logout"
    wire:loading.attr="disabled"
    wire:target="logout"
>
    <span wire:loading.remove wire:target="logout">Keluar</span>
    <span wire:loading wire:target="logout">Keluar...</span>
</x-admin.button>
