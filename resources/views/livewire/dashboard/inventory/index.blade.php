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
use function Livewire\Volt\usesPagination;
use function Livewire\Volt\with;

usesPagination();

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

with(fn () => [
    'items' => InventoryItem::query()->orderBy('name')->paginate(10),
]);
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

<div class="space-y-7">
    <x-admin.page-header
        title="Inventaris"
        description="Catat kondisi, lokasi, dan peminjaman barang milik RT."
    />

    <x-admin.panel>
        <form wire:submit="save" class="grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="inventory-name" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Nama Barang</label>
                    <input
                        id="inventory-name"
                        wire:model="name"
                        type="text"
                        class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Contoh: Tenda biru"
                    >
                    @error('name') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="inventory-condition" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Kondisi</label>
                    <select
                        id="inventory-condition"
                        wire:model="condition"
                        class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                        @foreach ($this->conditions as $conditionOption)
                            <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('condition') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="inventory-location" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Lokasi</label>
                    <input
                        id="inventory-location"
                        wire:model="location"
                        type="text"
                        class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="Contoh: Gudang sekretariat"
                    >
                    @error('location') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="inventory-notes" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Catatan</label>
                <textarea
                    id="inventory-notes"
                    wire:model="notes"
                    rows="2"
                    class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="Nomor aset atau detail tambahan..."
                ></textarea>
                @error('notes') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                @if ($editingId)
                    <x-admin.button type="button" variant="secondary" wire:click="resetForm">Batal</x-admin.button>
                @endif
                <x-admin.button type="submit">{{ $editingId ? 'Perbarui' : 'Tambah Barang' }}</x-admin.button>
            </div>
        </form>
    </x-admin.panel>

    <x-admin.panel :padding="false" aria-labelledby="inventory-list-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="inventory-list-title" class="text-lg font-medium text-ink">Daftar Inventaris</h2>
            <p class="mt-1 text-sm text-ink-mute">Kelola aset barang milik RT dan status ketersediaannya.</p>
        </div>

        <div class="divide-y divide-hairline">
            @forelse ($items as $item)
                <article wire:key="inventory-{{ $item->id }}" class="px-5 py-5 sm:px-6 hover:bg-canvas-soft/50 transition">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-medium text-ink">{{ $item->name }}</h3>
                                <x-admin.status-badge :tone="$item->status === ItemStatus::TERSEDIA ? 'success' : ($item->status === ItemStatus::DIPINJAM ? 'warning' : 'neutral')">
                                    {{ $item->status->label() }}
                                </x-admin.status-badge>
                                <x-admin.status-badge :tone="$item->condition === ItemCondition::BAIK ? 'info' : ($item->condition === ItemCondition::RUSAK_RINGAN ? 'warning' : 'danger')">
                                    {{ $item->condition->label() }}
                                </x-admin.status-badge>
                            </div>
                            <p class="mt-2 text-sm text-ink-mute">
                                <span class="font-medium text-ink-secondary">Lokasi:</span> {{ $item->location ?: 'Belum dicatat' }}
                                @if($item->isOnLoan())
                                    <span class="mx-1.5 text-hairline-input">&middot;</span>
                                    <span class="font-medium text-ink-secondary">Peminjam:</span> {{ $item->holder }}
                                @endif
                            </p>
                            @if ($item->notes)
                                <p class="mt-1.5 text-xs text-ink-mute italic">Catatan: {{ $item->notes }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <x-admin.button variant="secondary" wire:click="edit({{ $item->id }})">Edit</x-admin.button>

                            @if ($item->isOnLoan())
                                <x-admin.button wire:click="returnItem({{ $item->id }})">Kembalikan</x-admin.button>
                            @elseif ($item->status === ItemStatus::TERSEDIA)
                                <x-admin.button wire:click="startLend({{ $item->id }})">Pinjamkan</x-admin.button>
                            @endif

                            @if (! $item->isOnLoan())
                                <x-admin.button variant="danger" wire:click="toggleActive({{ $item->id }})">
                                    {{ $item->status === ItemStatus::TIDAK_AKTIF ? 'Aktifkan' : 'Nonaktifkan' }}
                                </x-admin.button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <x-admin.empty-state
                    title="Belum ada barang inventaris"
                    description="Tambahkan barang pertama untuk mulai mendata aset RT."
                />
            @endforelse
        </div>

        @if ($items->hasPages())
            <div class="border-t border-hairline px-5 py-4 sm:px-6">
                {{ $items->links() }}
            </div>
        @endif
    </x-admin.panel>

    <!-- Loan Dialog -->
    @if ($lendId)
        <div class="rounded-lg bg-white border border-hairline p-5 shadow-level2">
            <h2 class="text-base font-semibold text-ink">Catat peminjaman</h2>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="inventory-holder" class="block text-xs font-semibold text-ink-mute uppercase tracking-wider">Nama peminjam</label>
                    <input id="inventory-holder" wire:model="holder" type="text" class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-ink focus:border-primary focus:ring-1 focus:ring-primary transition-all text-base">
                    @error('holder') <p class="mt-1 text-xs text-ruby font-medium">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <x-admin.button type="button" wire:click="lend">Pinjamkan</x-admin.button>
                    <x-admin.button type="button" variant="secondary" wire:click="cancelLend">Batal</x-admin.button>
                </div>
            </div>
        </div>
    @endif
</div>
