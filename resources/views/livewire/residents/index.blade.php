<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\Audit;
use function Livewire\Volt\{state, computed, layout, title};

state([
    'editingId' => null,
    'household_id' => null,
    'name' => '',
    'phone' => '',
    'ronda_notes' => '',
]);

layout('components.layouts.app');
title('Data Warga');

$residents = computed(fn () => Resident::query()->with('household')->latest()->get());
$households = computed(fn () => Household::query()->where('is_active', true)->orderBy('house_number')->get());

$rulesFor = function (): array {
    $uniquePhone = app(UniqueActivePhone::class, ['ignoreResidentId' => $this->editingId]);

    return [
        'household_id' => ['required', 'exists:households,id'],
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:30', $uniquePhone],
        'ronda_notes' => ['nullable', 'string', 'max:500'],
    ];
};

$edit = function (int $id) {
    $resident = Resident::findOrFail($id);
    $this->editingId = $resident->id;
    $this->household_id = $resident->household_id;
    $this->name = $resident->name;
    $this->phone = $resident->phone;
    $this->ronda_notes = $resident->ronda_notes;
};

$resetForm = function () {
    $this->reset('editingId', 'household_id', 'name', 'phone', 'ronda_notes');
};

$save = function () {
    $data = $this->validate($this->rulesFor());

    if ($this->editingId) {
        $resident = Resident::findOrFail($this->editingId);
        $resident->update($data);
        Audit::record(auth()->user(), 'resident.updated', 'resident', $resident->id, ['name' => $resident->name]);
    } else {
        $resident = Resident::create($data);
        Audit::record(auth()->user(), 'resident.created', 'resident', $resident->id, ['name' => $resident->name]);
    }

    $this->resetForm();
};

$toggleActive = function (int $id) {
    $resident = Resident::findOrFail($id);
    $resident->update(['is_active' => ! $resident->is_active]);
    Audit::record(auth()->user(), 'resident.toggled', 'resident', $resident->id, ['is_active' => $resident->is_active]);
};

?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
        <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Master data</p>
        <h1 class="mt-2 display-lg text-[#0d253d]">Data Warga</h1>
        <p class="mt-2 text-[#64748d]">Kelola warga aktif, nomor HP unik, rumah/KK, dan catatan ronda.</p>
    </div>

    <!-- Form Section -->
    <section class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-[#0d253d]">{{ $editingId ? 'Perbarui Warga' : 'Tambah Warga Baru' }}</h2>
            <p class="mt-1 text-sm text-[#64748d]">Pilih rumah/KK, isi nama, dan nomor HP aktif warga.</p>
        </div>

        <form wire:submit="save" class="grid gap-4 sm:grid-cols-5 items-end">
            <div>
                <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Rumah/KK</label>
                <select wire:model="household_id" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                    <option value="">Pilih rumah</option>
                    @foreach ($this->households as $household)
                        <option value="{{ $household->id }}">{{ $household->house_number }} - {{ $household->head_name }}</option>
                    @endforeach
                </select>
                @error('household_id') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nama</label>
                <input wire:model="name" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                @error('name') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nomor HP</label>
                <input wire:model="phone" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
                @error('phone') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Catatan Ronda</label>
                <input wire:model="ronda_notes" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all duration-300 text-base">
            </div>
            <div class="flex items-center gap-2">
                <button class="rounded-full bg-[#533afd] px-5 py-3 font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition duration-150 text-xs">
                    {{ $editingId ? 'Perbarui' : 'Tambah' }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-4 py-3 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Batal</button>
                @endif
            </div>
        </form>
    </section>

    <!-- Table Section -->
    <section class="rounded-lg bg-white border border-[#e3e8ee] overflow-hidden shadow-level1">
        <div class="border-b border-[#e3e8ee] px-5 py-4">
            <h2 class="text-base font-semibold text-[#0d253d]">Daftar Warga</h2>
            <p class="mt-1 text-xs text-[#64748d]">Nomor HP adalah identitas warga aktif untuk akses layanan.</p>
        </div>

        <div class="hidden overflow-hidden sm:block">
            <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                <thead class="bg-[#f6f9fc] text-left text-[#64748d]">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Nomor HP</th>
                        <th class="px-5 py-3 font-semibold">Rumah</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e3e8ee] text-[#0d253d]">
                    @foreach ($this->residents as $resident)
                        <tr class="hover:bg-[#f6f9fc]/50 transition-colors">
                            <td class="px-5 py-3 font-semibold text-[#0d253d]">{{ $resident->name }}</td>
                            <td class="px-5 py-3 text-[#273951] tnum">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</td>
                            <td class="px-5 py-3 text-[#273951] tnum">{{ $resident->household?->house_number }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                                    {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                                    <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $resident->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                                        {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-[#e3e8ee] sm:hidden">
            @foreach ($this->residents as $resident)
                <article class="p-5 hover:bg-[#f6f9fc]/50 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-[#0d253d]">{{ $resident->name }}</h3>
                            <p class="mt-1 text-sm text-[#64748d]">No. <span class="tnum">{{ $resident->household?->house_number ?? '-' }}</span> · HP: <span class="tnum">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</span></p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-slate-500/10 border border-slate-500/20 text-slate-600' }}">
                            {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    @if ($resident->ronda_notes)
                        <p class="mt-3 rounded-sm bg-slate-50 border border-[#e3e8ee] p-3 text-xs text-[#64748d]">{{ $resident->ronda_notes }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-3 py-1.5 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Edit</button>
                        <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-1.5 text-xs font-semibold transition duration-150 {{ $resident->is_active ? 'bg-red-500/10 border border-red-500/30 text-red-600 hover:bg-red-500/20' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/20' }}">
                            {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
