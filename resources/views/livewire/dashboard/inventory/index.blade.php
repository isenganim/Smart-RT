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
    <!-- Header Section -->
    <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
        <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Aset RT</p>
        <h1 class="mt-2 display-lg text-[#0d253d]">Inventaris</h1>
        <p class="mt-2 text-[#64748d]">Catat kondisi, lokasi, dan peminjaman barang milik RT.</p>
    </div>

    <!-- Form Section -->
    <form wire:submit="save" class="grid gap-4 rounded-lg bg-white p-5 border border-[#e3e8ee] shadow-level1 sm:grid-cols-2 lg:grid-cols-6 items-end">
        <div class="sm:col-span-2 lg:col-span-2">
            <label for="inventory-name" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nama barang</label>
            <input id="inventory-name" wire:model="name" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base" placeholder="Contoh: Tenda biru">
            @error('name') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="inventory-condition" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Kondisi</label>
            <select id="inventory-condition" wire:model="condition" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base">
                @foreach ($this->conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}" class="bg-white text-[#0d253d]">{{ $conditionOption->label() }}</option>
                @endforeach
            </select>
            @error('condition') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2 lg:col-span-2">
            <label for="inventory-location" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Lokasi</label>
            <input id="inventory-location" wire:model="location" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base" placeholder="Gudang atau sekretariat">
            @error('location') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2 lg:col-span-5">
            <label for="inventory-notes" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Catatan</label>
            <textarea id="inventory-notes" wire:model="notes" rows="2" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base" placeholder="Nomor aset atau detail tambahan"></textarea>
            @error('notes') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-full bg-[#533afd] px-5 py-3 font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition duration-150 text-xs">
                {{ $editingId ? 'Perbarui' : 'Tambah' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-4 py-3 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Batal</button>
            @endif
        </div>
    </form>

    <!-- Cards Grid -->
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($this->items as $item)
            <article wire:key="inventory-{{ $item->id }}" class="rounded-lg bg-white border border-[#e3e8ee] p-5 shadow-level1 hover:border-[#533afd]/50 hover:shadow-level2 transition-all">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-[#0d253d]">{{ $item->name }}</h2>
                        <p class="mt-1 text-sm text-[#64748d]">
                            {{ $item->isOnLoan() ? 'Peminjam: '.$item->holder : ($item->location ?: 'Lokasi belum dicatat') }}
                        </p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold border',
                        'bg-emerald-500/10 border-emerald-500/20 text-emerald-600' => $item->status === ItemStatus::TERSEDIA,
                        'bg-amber-500/10 border-amber-500/20 text-amber-600' => $item->status === ItemStatus::DIPINJAM,
                        'bg-slate-500/10 border-slate-500/20 text-slate-600' => $item->status === ItemStatus::TIDAK_AKTIF,
                    ])>
                        {{ $item->status->label() }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    <span @class([
                        'rounded-full px-2.5 py-0.5 font-semibold border',
                        'bg-sky-500/10 border-sky-500/20 text-sky-600' => $item->condition === ItemCondition::BAIK,
                        'bg-orange-500/10 border-orange-500/20 text-orange-600' => $item->condition === ItemCondition::RUSAK_RINGAN,
                        'bg-red-500/10 border-red-500/20 text-red-600' => $item->condition === ItemCondition::RUSAK_BERAT,
                    ])>
                        {{ $item->condition->label() }}
                    </span>
                    @if ($item->notes)
                        <span class="text-[#64748d] text-xs italic ml-2">{{ $item->notes }}</span>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap gap-3 border-t border-[#e3e8ee] pt-4 text-xs font-medium">
                    <button wire:click="edit({{ $item->id }})" class="text-[#64748d] hover:text-[#0d253d] transition duration-150">Edit</button>

                    @if ($item->isOnLoan())
                        <button wire:click="returnItem({{ $item->id }})" class="text-[#533afd] hover:text-[#4434d4] transition duration-150">Kembalikan</button>
                    @elseif ($item->status === ItemStatus::TERSEDIA)
                        <button wire:click="startLend({{ $item->id }})" class="text-[#533afd] hover:text-[#4434d4] transition duration-150">Pinjamkan</button>
                    @endif

                    @if (! $item->isOnLoan())
                        <button wire:click="toggleActive({{ $item->id }})" class="text-[#64748d] hover:text-[#0d253d] transition duration-150">
                            {{ $item->status === ItemStatus::TIDAK_AKTIF ? 'Aktifkan' : 'Nonaktifkan' }}
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-[#e3e8ee] bg-white p-8 text-center text-[#64748d] md:col-span-2 shadow-level1">
                Belum ada barang inventaris.
            </div>
        @endforelse
    </div>

    <!-- Loan Dialog -->
    @if ($lendId)
        <div class="rounded-lg bg-white border border-[#e3e8ee] p-5 shadow-level2">
            <h2 class="text-base font-semibold text-[#0d253d]">Catat peminjaman</h2>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="inventory-holder" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nama peminjam</label>
                    <input id="inventory-holder" wire:model="holder" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-[#0d253d] placeholder-slate-400 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd] transition-all text-base">
                    @error('holder') <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="lend" class="rounded-full bg-[#533afd] px-5 py-3 font-semibold text-white shadow-level1 hover:bg-[#4434d4] active:bg-[#2e2b8c] transition duration-150 text-xs">Pinjamkan</button>
                    <button type="button" wire:click="cancelLend" class="rounded-full bg-slate-100 border border-[#e3e8ee] px-4 py-3 text-xs font-semibold text-[#0d253d] hover:bg-slate-200 transition duration-150">Batal</button>
                </div>
            </div>
        </div>
    @endif
</div>
