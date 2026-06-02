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
        <div class="rounded-[1.5rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:rounded-[1.75rem]">
            <p class="text-sm font-semibold text-emerald-700">Master data</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Data Warga</h1>
            <p class="mt-2 text-slate-500">Kelola warga aktif, nomor HP unik, rumah/KK, dan catatan ronda.</p>
        </div>

        <section class="rounded-[1.5rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-slate-950">{{ $editingId ? 'Perbarui Warga' : 'Tambah Warga Baru' }}</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih rumah/KK, isi nama, dan nomor HP aktif warga.</p>
            </div>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Rumah/KK</label>
                    <select wire:model="household_id" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                        <option value="">Pilih rumah</option>
                        @foreach ($this->households as $household)
                            <option value="{{ $household->id }}">{{ $household->house_number }} - {{ $household->head_name }}</option>
                        @endforeach
                    </select>
                    @error('household_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nama</label>
                    <input wire:model="name" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nomor HP</label>
                    <input wire:model="phone" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Catatan Ronda</label>
                    <input wire:model="ronda_notes" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                </div>
                <div class="flex items-end gap-2">
                    <button class="rounded-2xl bg-emerald-500 px-5 py-3 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400">
                        {{ $editingId ? 'Perbarui' : 'Tambah' }}
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="resetForm" class="rounded-2xl px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Batal</button>
                    @endif
                </div>
            </form>
        </section>

        <section class="rounded-[1.5rem] bg-white shadow-xl shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-950">Daftar Warga</h2>
                <p class="mt-1 text-sm text-slate-500">Nomor HP adalah identitas warga aktif untuk akses layanan.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Nomor HP</th>
                            <th class="px-4 py-3">Rumah</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->residents as $resident)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $resident->name }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</td>
                                <td class="px-4 py-3">{{ $resident->household?->house_number }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">Edit</button>
                                        <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $resident->is_active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                            {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($this->residents as $resident)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $resident->name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">No. {{ $resident->household?->house_number ?? '-' }} · HP: <span class="tabular-nums">{{ blank($resident->phone) ? '-' : (str_starts_with($resident->phone, '0') || str_starts_with($resident->phone, '+') ? $resident->phone : '0'.$resident->phone) }}</span></p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $resident->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        @if ($resident->ronda_notes)
                            <p class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $resident->ronda_notes }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button wire:click="edit({{ $resident->id }})" class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700">Edit</button>
                            <button wire:click="toggleActive({{ $resident->id }})" class="rounded-full px-3 py-2 text-xs font-bold {{ $resident->is_active ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
</div>
