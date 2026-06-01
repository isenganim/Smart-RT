<?php

use App\Models\Household;
use App\Support\Audit;
use function Livewire\Volt\{state, rules, computed};

state([
    'editingId' => null,
    'house_number' => '',
    'address' => '',
    'head_name' => '',
]);

rules([
    'house_number' => ['required', 'string', 'max:255'],
    'address' => ['nullable', 'string', 'max:255'],
    'head_name' => ['required', 'string', 'max:255'],
]);

$households = computed(fn () => Household::query()->latest()->get());

$edit = function (int $id) {
    $household = Household::findOrFail($id);
    $this->editingId = $household->id;
    $this->house_number = $household->house_number;
    $this->address = $household->address;
    $this->head_name = $household->head_name;
};

$resetForm = function () {
    $this->reset('editingId', 'house_number', 'address', 'head_name');
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
};

$toggleActive = function (int $id) {
    $household = Household::findOrFail($id);
    $household->update(['is_active' => ! $household->is_active]);
    Audit::record(auth()->user(), 'household.toggled', 'household', $household->id, ['is_active' => $household->is_active]);
};

?>

<x-layouts.app title="Data Rumah/KK">
    <div class="space-y-6">
        <div class="rounded-[1.5rem] bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-200 sm:rounded-[1.75rem]">
            <p class="text-sm font-semibold text-emerald-700">Master data</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Data Rumah/KK</h1>
            <p class="mt-2 text-slate-500">Kelola rumah, kepala keluarga, status aktif, dan QR token per rumah.</p>
        </div>

        <section class="rounded-[1.5rem] bg-white p-5 shadow-lg shadow-slate-900/5 ring-1 ring-slate-200">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-slate-950">{{ $editingId ? 'Perbarui Rumah/KK' : 'Tambah Rumah/KK Baru' }}</h2>
                <p class="mt-1 text-sm text-slate-500">Isi nomor rumah dan nama kepala keluarga untuk operasional RT.</p>
            </div>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-4">
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-slate-700">Nomor Rumah</label>
                    <input wire:model="house_number" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    @error('house_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-slate-700">Alamat</label>
                    <input wire:model="address" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-slate-700">Kepala Keluarga</label>
                    <input wire:model="head_name" type="text" class="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                    @error('head_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                <h2 class="text-lg font-bold text-slate-950">Daftar Rumah/KK</h2>
                <p class="mt-1 text-sm text-slate-500">Status aktif menentukan rumah yang dipakai untuk operasional kas dan ronda.</p>
            </div>

            <div class="hidden overflow-hidden sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nomor</th>
                            <th class="px-4 py-3">Kepala Keluarga</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->households as $household)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $household->house_number }}</td>
                                <td class="px-4 py-3">{{ $household->head_name }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100">QR</a>
                                        <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200">Edit</button>
                                        <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-1.5 text-xs font-bold {{ $household->is_active ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                            {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($this->households as $household)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-950">{{ $household->house_number }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $household->head_name }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $household->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('households.qr', $household) }}" class="rounded-full bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">QR</a>
                            <button wire:click="edit({{ $household->id }})" class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700">Edit</button>
                            <button wire:click="toggleActive({{ $household->id }})" class="rounded-full px-3 py-2 text-xs font-bold {{ $household->is_active ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
