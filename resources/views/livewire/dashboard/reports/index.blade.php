<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{computed, layout, rules, state, title, usesPagination, with};

usesPagination();

layout('components.layouts.app');
title('Laporan Warga');

state(['updateId' => null, 'status' => '', 'notes' => '', 'filter' => 'open']);
rules(['status' => ['required', Rule::enum(ReportStatus::class)], 'notes' => ['nullable', 'string', 'max:2000']]);

$updatedFilter = function () {
    $this->resetPage();
};

with(function () {
    $query = Report::with('resident')->latest();
    if ($this->filter === 'open') {
        $query->open();
    }
    return [
        'reports' => $query->paginate(10),
    ];
});

$statuses = computed(fn () => ReportStatus::cases());
$startUpdate = function (int $id) { $item = Report::findOrFail($id); $this->updateId = $id; $this->status = $item->status->value; $this->notes = $item->notes ?? ''; };
$saveUpdate = function () {
    $this->validate();

    DB::transaction(function () {
        $item = Report::findOrFail($this->updateId);
        $from = $item->status->value;
        $item->update(['status' => $this->status, 'notes' => $this->notes]);
        Audit::record(auth()->user(), 'report.status_changed', 'report', $item->id, ['from' => $from, 'to' => $this->status]);
    });

    $this->reset('updateId', 'status', 'notes');
};
?>
<div class="space-y-6">
    <div class="flex justify-between items-center bg-white p-4 rounded-xl border border-hairline shadow-level1">
        <h1 class="text-xl font-semibold text-ink">Laporan Warga</h1>
        <select wire:model.live="filter" class="rounded-md border border-hairline-input bg-white px-3 py-1.5 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary">
            <option value="open">Belum selesai</option>
            <option value="all">Semua</option>
        </select>
    </div>

    <div class="space-y-4">
        @forelse($reports as $item)
            <div class="rounded-xl bg-white p-5 border border-hairline shadow-level1">
                <div class="flex justify-between items-start gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold tracking-wider text-ink-mute uppercase">{{ $item->category }}</p>
                        <p class="text-base text-ink font-medium">{{ $item->description }}</p>
                        <p class="text-xs text-ink-mute">Pengirim: {{ $item->resident?->name ?? 'Warga' }} · {{ $item->phone }}</p>
                        @if($item->notes)
                            <div class="mt-3 rounded bg-canvas-soft border border-hairline p-3 text-sm text-ink-secondary">
                                <span class="font-semibold text-ink block mb-0.5">Catatan Tindak Lanjut:</span>
                                {{ $item->notes }}
                            </div>
                        @endif
                    </div>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold border shrink-0',
                        'bg-blue-500/10 border-blue-500/20 text-blue-600' => $item->status === ReportStatus::BARU,
                        'bg-amber-500/10 border-amber-500/20 text-amber-600' => $item->status === ReportStatus::DIPROSES,
                        'bg-emerald-500/10 border-emerald-500/20 text-emerald-600' => $item->status === ReportStatus::SELESAI,
                        'bg-red-500/10 border-red-500/20 text-red-600' => $item->status === ReportStatus::DITOLAK,
                    ])>
                        {{ $item->status->label() }}
                    </span>
                </div>

                @if($updateId === $item->id)
                    <div class="mt-4 flex flex-col sm:flex-row gap-3 border-t border-hairline pt-4">
                        <div class="flex-1 flex gap-2">
                            <select wire:model="status" class="rounded-md border border-hairline-input bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary min-w-[140px]">
                                @foreach($this->statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            <input wire:model="notes" placeholder="Tambahkan catatan tindak lanjut..." class="flex-1 rounded-md border border-hairline-input bg-white px-3 py-2 text-sm text-ink focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button wire:click="saveUpdate" class="rounded-full bg-primary px-5 py-2.5 text-xs font-semibold text-white shadow-level1 hover:bg-primary-deep transition">Simpan</button>
                            <button wire:click="$reset('updateId')" class="rounded-full bg-slate-100 border border-hairline px-4 py-2.5 text-xs font-semibold text-ink-secondary hover:bg-slate-200 transition">Batal</button>
                        </div>
                    </div>
                @else
                    <div class="mt-4 border-t border-hairline pt-3 flex justify-end">
                        <button wire:click="startUpdate({{ $item->id }})" class="text-xs font-semibold text-primary hover:text-primary-deep transition">Tindak Lanjuti &rarr;</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-hairline bg-white p-8 text-center text-ink-mute shadow-level1">
                Tidak ada laporan warga.
            </div>
        @endforelse
    </div>

    @if($reports->hasPages())
        <div class="bg-white p-4 rounded-xl border border-hairline shadow-level1">
            {{ $reports->links() }}
        </div>
    @endif
</div>
