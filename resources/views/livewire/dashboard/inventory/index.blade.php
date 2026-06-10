<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\InventoryItem;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

layout('components.layouts.app');
title('Inventaris');

state([
    'editingId' => null,
    'name' => '',
    'condition' => 'baik',
    'location' => '',
    'notes' => '',
    'lendId' => null,
    'holder' => '',
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'condition' => ['required', Rule::enum(ItemCondition::class)],
    'location' => ['nullable', 'string', 'max:255'],
    'notes' => ['nullable', 'string', 'max:2000'],
]);

$items = computed(fn () => InventoryItem::query()->orderBy('name')->get());
$conditions = computed(fn () => ItemCondition::cases());

$edit = function (int $id) {
    $item = InventoryItem::findOrFail($id);

    $this->editingId = $item->id;
    $this->name = $item->name;
    $this->condition = $item->condition->value;
    $this->location = $item->location ?? '';
    $this->notes = $item->notes ?? '';
};

$resetForm = function () {
    $this->reset('editingId', 'name', 'condition', 'location', 'notes');
    $this->resetValidation();
};

$save = function () {
    $data = $this->validate();

    DB::transaction(function () use ($data) {
        if ($this->editingId) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($this->editingId);
            $before = $item->only(['name', 'condition', 'location', 'notes']);
            $item->update($data);
            $action = 'inventory.updated';
            $metadata = [
                'before' => $before,
                'after' => $item->only(['name', 'condition', 'location', 'notes']),
            ];
        } else {
            $item = InventoryItem::query()->create($data);
            $action = 'inventory.created';
            $metadata = ['name' => $item->name];
        }

        Audit::record(auth()->user(), $action, 'inventory_item', $item->id, $metadata);
    });

    $this->resetForm();
};

$startLend = function (int $id) {
    $this->resetValidation('holder');
    $this->lendId = $id;
    $this->holder = '';
};

$cancelLend = function () {
    $this->reset('lendId', 'holder');
    $this->resetValidation('holder');
};

$lend = function () {
    $this->validate(['holder' => ['required', 'string', 'max:255']]);

    DB::transaction(function () {
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($this->lendId);

        if ($item->status !== ItemStatus::TERSEDIA) {
            $this->addError('holder', 'Barang ini tidak tersedia untuk dipinjamkan.');

            return;
        }

        $item->update([
            'status' => ItemStatus::DIPINJAM,
            'holder' => $this->holder,
        ]);

        Audit::record(auth()->user(), 'inventory.lent', 'inventory_item', $item->id, [
            'holder' => $this->holder,
        ]);
    });

    if ($this->getErrorBag()->has('holder')) {
        return;
    }

    $this->cancelLend();
};

$returnItem = function (int $id) {
    DB::transaction(function () use ($id) {
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($id);

        if ($item->status !== ItemStatus::DIPINJAM) {
            return;
        }

        $holder = $item->holder;
        $item->update([
            'status' => ItemStatus::TERSEDIA,
            'holder' => null,
        ]);

        Audit::record(auth()->user(), 'inventory.returned', 'inventory_item', $item->id, [
            'holder' => $holder,
        ]);
    });
};

$toggleActive = function (int $id) {
    DB::transaction(function () use ($id) {
        $item = InventoryItem::query()->lockForUpdate()->findOrFail($id);

        if ($item->status === ItemStatus::DIPINJAM) {
            return;
        }

        $from = $item->status;
        $to = $from === ItemStatus::TIDAK_AKTIF
            ? ItemStatus::TERSEDIA
            : ItemStatus::TIDAK_AKTIF;

        $item->update([
            'status' => $to,
            'holder' => null,
        ]);

        Audit::record(auth()->user(), 'inventory.status_changed', 'inventory_item', $item->id, [
            'from' => $from->value,
            'to' => $to->value,
        ]);
    });
};

?>

<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300 sm:text-emerald-700">Aset RT</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-slate-900">Inventaris</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-300 sm:text-slate-600">Catat kondisi, lokasi, dan peminjaman barang milik RT.</p>
    </div>

    <form wire:submit="save" class="grid gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2 lg:col-span-2">
            <label for="inventory-name" class="block text-sm font-medium text-slate-700">Nama barang</label>
            <input id="inventory-name" wire:model="name" type="text" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Contoh: Tenda biru">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="inventory-condition" class="block text-sm font-medium text-slate-700">Kondisi</label>
            <select id="inventory-condition" wire:model="condition" class="mt-1 w-full rounded-xl border-slate-300">
                @foreach ($this->conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                @endforeach
            </select>
            @error('condition') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2 lg:col-span-2">
            <label for="inventory-location" class="block text-sm font-medium text-slate-700">Lokasi</label>
            <input id="inventory-location" wire:model="location" type="text" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Gudang atau sekretariat">
            @error('location') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2 lg:col-span-5">
            <label for="inventory-notes" class="block text-sm font-medium text-slate-700">Catatan</label>
            <textarea id="inventory-notes" wire:model="notes" rows="2" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Nomor aset atau detail tambahan"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 font-semibold text-white transition hover:bg-emerald-700">
                {{ $editingId ? 'Perbarui' : 'Tambah' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Batal</button>
            @endif
        </div>
    </form>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($this->items as $item)
            <article wire:key="inventory-{{ $item->id }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $item->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $item->isOnLoan() ? 'Peminjam: '.$item->holder : ($item->location ?: 'Lokasi belum dicatat') }}
                        </p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-emerald-100 text-emerald-700' => $item->status === ItemStatus::TERSEDIA,
                        'bg-amber-100 text-amber-700' => $item->status === ItemStatus::DIPINJAM,
                        'bg-slate-100 text-slate-500' => $item->status === ItemStatus::TIDAK_AKTIF,
                    ])>
                        {{ $item->status->label() }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span @class([
                        'rounded-full px-2.5 py-1 font-medium',
                        'bg-sky-100 text-sky-700' => $item->condition === ItemCondition::BAIK,
                        'bg-orange-100 text-orange-700' => $item->condition === ItemCondition::RUSAK_RINGAN,
                        'bg-red-100 text-red-700' => $item->condition === ItemCondition::RUSAK_BERAT,
                    ])>
                        {{ $item->condition->label() }}
                    </span>
                    @if ($item->notes)
                        <span class="text-slate-500">{{ $item->notes }}</span>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-4 text-sm font-medium">
                    <button wire:click="edit({{ $item->id }})" class="text-slate-600 hover:text-slate-900">Edit</button>

                    @if ($item->isOnLoan())
                        <button wire:click="returnItem({{ $item->id }})" class="text-emerald-700 hover:text-emerald-900">Kembalikan</button>
                    @elseif ($item->status === ItemStatus::TERSEDIA)
                        <button wire:click="startLend({{ $item->id }})" class="text-emerald-700 hover:text-emerald-900">Pinjamkan</button>
                    @endif

                    @if (! $item->isOnLoan())
                        <button wire:click="toggleActive({{ $item->id }})" class="text-slate-500 hover:text-slate-800">
                            {{ $item->status === ItemStatus::TIDAK_AKTIF ? 'Aktifkan' : 'Nonaktifkan' }}
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 p-8 text-center text-slate-500 md:col-span-2">
                Belum ada barang inventaris.
            </div>
        @endforelse
    </div>

    @if ($lendId)
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-2 ring-emerald-200">
            <h2 class="font-semibold text-slate-900">Catat peminjaman</h2>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="inventory-holder" class="block text-sm font-medium text-slate-700">Nama peminjam</label>
                    <input id="inventory-holder" wire:model="holder" type="text" class="mt-1 w-full rounded-xl border-slate-300">
                    @error('holder') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="lend" class="rounded-xl bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-700">Pinjamkan</button>
                    <button type="button" wire:click="cancelLend" class="rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Batal</button>
                </div>
            </div>
        </div>
    @endif
</div>
