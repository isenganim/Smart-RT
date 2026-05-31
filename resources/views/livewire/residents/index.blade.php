<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\Audit;
use function Livewire\Volt\{state, computed};

state([
    'editingId' => null,
    'household_id' => null,
    'name' => '',
    'phone' => '',
    'ronda_notes' => '',
]);

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

<x-layouts.app title="Data Warga">
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-slate-900">Data Warga</h1>

        <form wire:submit="save" class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">Rumah/KK</label>
                <select wire:model="household_id" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Pilih rumah</option>
                    @foreach ($this->households as $household)
                        <option value="{{ $household->id }}">{{ $household->house_number }} - {{ $household->head_name }}</option>
                    @endforeach
                </select>
                @error('household_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nama</label>
                <input wire:model="name" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nomor HP</label>
                <input wire:model="phone" type="text" class="mt-1 w-full rounded-lg border-slate-300">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Catatan Ronda</label>
                <input wire:model="ronda_notes" type="text" class="mt-1 w-full rounded-lg border-slate-300">
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
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Nomor HP</th>
                        <th class="px-4 py-2">Rumah</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->residents as $resident)
                        <tr>
                            <td class="px-4 py-2">{{ $resident->name }}</td>
                            <td class="px-4 py-2">{{ $resident->phone }}</td>
                            <td class="px-4 py-2">{{ $resident->household?->house_number }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $resident->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $resident->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <button wire:click="edit({{ $resident->id }})" class="text-slate-600 hover:underline">Edit</button>
                                <button wire:click="toggleActive({{ $resident->id }})" class="ml-3 text-slate-600 hover:underline">
                                    {{ $resident->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
