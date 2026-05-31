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
        <h1 class="text-2xl font-semibold text-slate-900">Data Rumah/KK</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Nomor Rumah</label>
                <input wire:model="house_number" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('house_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Alamat</label>
                <input wire:model="address" type="text" class="mt-1 w-full rounded-lg border-slate-300">
            </div>
            <div class="sm:col-span-1">
                <label class="block text-sm font-medium text-slate-700">Kepala Keluarga</label>
                <input wire:model="head_name" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('head_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">
                    {{ $editingId ? 'Perbarui' : 'Tambah' }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-lg px-3 py-2 text-slate-600 hover:text-slate-900">Batal</button>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Nomor</th>
                        <th class="px-4 py-2">Kepala Keluarga</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->households as $household)
                        <tr>
                            <td class="px-4 py-2">{{ $household->house_number }}</td>
                            <td class="px-4 py-2">{{ $household->head_name }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $household->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $household->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('households.qr', $household) }}" class="text-emerald-700 hover:underline">QR</a>
                                <button wire:click="edit({{ $household->id }})" class="ml-3 text-slate-600 hover:underline">Edit</button>
                                <button wire:click="toggleActive({{ $household->id }})" class="ml-3 text-slate-600 hover:underline">
                                    {{ $household->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
