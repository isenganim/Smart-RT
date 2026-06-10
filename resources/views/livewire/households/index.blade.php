<?php

use App\Models\Household;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed, layout, title};

state([
    'editingId' => null,
    'house_number' => '',
    'address' => '',
    'head_name' => '',
    'showFormModal' => false,
]);

layout('components.layouts.app');
title('Data Rumah/KK');

rules([
    'house_number' => ['required', 'string', 'max:255'],
    'address' => ['nullable', 'string', 'max:255'],
    'head_name' => ['required', 'string', 'max:255'],
]);

$households = computed(fn () => Household::query()->latest()->get());

$openCreateModal = function () {
    $this->resetForm();
    $this->showFormModal = true;
};

$edit = function (int $id) {
    $household = Household::findOrFail($id);
    $this->editingId = $household->id;
    $this->house_number = $household->house_number;
    $this->address = $household->address;
    $this->head_name = $household->head_name;
    $this->showFormModal = true;
};

$resetForm = function () {
    $this->reset('editingId', 'house_number', 'address', 'head_name');
};

$closeFormModal = function () {
    $this->resetForm();
    $this->showFormModal = false;
};

$save = function () {
    $data = $this->validate();

    if ($this->editingId) {
        $household = Household::findOrFail($this->editingId);
        $household->update($data);
        Audit::record(auth()->user(), 'household.updated', 'household', $household->id, $data);
    } else {
        $household = Household::create($data);
        Audit::record(auth()->user(), 'household.created', 'household', $household->id, $data);
    }

    $this->resetForm();
    $this->showFormModal = false;
};

$toggleActive = function (int $id) {
    $household = Household::findOrFail($id);
    $household->update(['is_active' => ! $household->is_active]);
    Audit::record(auth()->user(), 'household.toggled', 'household', $household->id, ['is_active' => $household->is_active]);
};

?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Master data</p>
                <h1 class="mt-2 display-lg text-[#0d253d]">Data Rumah/KK</h1>
                <p class="mt-2 text-[#64748d]">Kelola rumah, kepala keluarga, status aktif, dan QR token per rumah.</p>
            </div>
            <button type="button" wire:click="openCreateModal" class="inline-flex items-center justify-center rounded-full bg-[#533afd] px-5 py-3 text-sm font-semibold text-white shadow-level1 transition duration-150 hover:bg-[#4434d4] active:bg-[#2e2b8c]">
                Tambah Rumah/KK Baru
            </button>
        </div>
    </div>

    @if ($showFormModal)
        <div wire:keydown.escape.window="closeFormModal" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6" role="dialog" aria-modal="true" aria-labelledby="household-modal-title">
            <button type="button" wire:click="closeFormModal" class="absolute inset-0 cursor-default bg-[#0d253d]/45 backdrop-blur-sm" aria-label="Tutup modal"></button>

            <section class="relative z-10 w-full max-w-2xl overflow-hidden rounded-xl border border-[#e3e8ee] bg-white shadow-level2">
                <div class="border-b border-[#e3e8ee] px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="household-modal-title" class="heading-md text-[#0d253d]">{{ $editingId ? 'Perbarui Rumah/KK' : 'Tambah Rumah/KK Baru' }}</h2>
                            <p class="mt-1 text-sm text-[#64748d]">Isi nomor rumah dan nama kepala keluarga untuk operasional RT.</p>
                        </div>
                        <button type="button" wire:click="closeFormModal" class="rounded-full border border-[#e3e8ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#64748d] transition hover:bg-[#f6f9fc] hover:text-[#0d253d]">
                            Tutup
                        </button>
                    </div>
                </div>

                <form wire:submit="save">
                    <div class="grid gap-4 px-6 py-6 sm:grid-cols-2">
                        <div>
                            <label for="house_number" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nomor Rumah</label>
                            <input wire:model="house_number" id="house_number" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                            @error('house_number') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Alamat</label>
                            <input wire:model="address" id="address" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                            @error('address') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="head_name" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Kepala Keluarga</label>
                            <input wire:model="head_name" id="head_name" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                            @error('head_name') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-[#e3e8ee] bg-[#f6f9fc] px-6 py-4 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="closeFormModal" class="rounded-full border border-[#e3e8ee] bg-white px-5 py-3 text-sm font-semibold text-[#0d253d] transition duration-150 hover:bg-slate-100">
                            Batal
                        </button>
                        <button class="rounded-full bg-[#533afd] px-5 py-3 text-sm font-semibold text-white shadow-level1 transition duration-150 hover:bg-[#4434d4] active:bg-[#2e2b8c]">
                            {{ $editingId ? 'Perbarui' : 'Tambah' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    <!-- Table Section -->
    <section class="rounded-lg bg-white border border-[#e3e8ee] overflow-hidden shadow-level1">
        <div class="border-b border-[#e3e8ee] px-5 py-4">
            <h2 class="text-base font-semibold text-[#0d253d]">Daftar Rumah/KK</h2>
            <p class="mt-1 text-xs text-[#64748d]">Status aktif menentukan rumah yang dipakai untuk operasional kas dan ronda.</p>
        </div>

        <div class="hidden overflow-hidden sm:block">
            <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                <thead class="bg-[#f6f9fc] text-left text-[#64748d]">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-5 py-3 font-semibold">Kepala Keluarga</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e3e8ee] text-[#0d253d]">
                    @foreach ($this->households as $household)
                        <tr class="hover:bg-[#f6f9fc]/50 transition-colors">
                            <td class="px-5 py-3 font-semibold text-[#0d253d] tnum">{{ $household->house_number }}</td>
                            <td class="px-5 py-3 text-[#273951]">{{ $household->head_name }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                    {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-[#533afd]/10 border border-[#533afd]/30 px-3 py-1.5 text-xs font-semibold text-[#533afd] hover:bg-[#533afd]/20 transition duration-150">QR</a>
                                    <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                                    <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $household->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                        {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-[#e3e8ee] sm:hidden">
            @foreach ($this->households as $household)
                <article class="p-5 hover:bg-[#f6f9fc]/50 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-[#0d253d] tnum">{{ $household->house_number }}</h3>
                            <p class="mt-1 text-sm text-[#64748d]">{{ $household->head_name }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                            {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-[#533afd]/10 border border-[#533afd]/30 px-3 py-1.5 text-xs font-semibold text-[#533afd] hover:bg-[#533afd]/20 transition duration-150">QR</a>
                        <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                        <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $household->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                            {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
