<?php

use App\Enums\LetterStatus;
use App\Models\LetterRequest;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.app'); title('Surat Pengantar');
state(['updateId' => null, 'status' => '', 'notes' => '', 'filter' => 'pending']);
rules(['status' => ['required', Rule::enum(LetterStatus::class)], 'notes' => ['nullable', 'string', 'max:2000']]);
$letters = computed(function () { $query = LetterRequest::with('resident')->latest(); if ($this->filter === 'pending') $query->pending(); return $query->get(); });
$statuses = computed(fn () => LetterStatus::cases());
$startUpdate = function (int $id) { $item = LetterRequest::findOrFail($id); $this->updateId = $id; $this->status = $item->status->value; $this->notes = $item->notes ?? ''; };
$saveUpdate = function () {
    $this->validate();

    DB::transaction(function () {
        $item = LetterRequest::findOrFail($this->updateId);
        $from = $item->status->value;
        $item->update(['status' => $this->status, 'notes' => $this->notes]);
        Audit::record(auth()->user(), 'letter.status_changed', 'letter_request', $item->id, ['from' => $from, 'to' => $this->status]);
    });

    $this->reset('updateId', 'status', 'notes');
};
?>
<div class="space-y-6"><div class="flex justify-between"><h1 class="text-2xl font-semibold text-white sm:text-slate-900">Surat Pengantar</h1><select wire:model.live="filter" class="rounded-lg border-slate-300"><option value="pending">Perlu diproses</option><option value="all">Semua</option></select></div><div class="space-y-3">@forelse($this->letters as $item)<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div class="flex justify-between gap-4"><div><p class="font-medium">{{ $item->type->label() }}</p><p class="text-sm">{{ $item->purpose }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->resident?->name ?? 'Warga' }} · {{ $item->phone }}</p>@if($item->notes)<p class="mt-2 text-sm text-slate-600">Catatan: {{ $item->notes }}</p>@endif</div><span class="text-xs">{{ $item->status->label() }}</span></div>@if($updateId === $item->id)<div class="mt-3 flex flex-wrap gap-2 border-t pt-3"><select wire:model="status" class="rounded-lg border-slate-300">@foreach($this->statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select><input wire:model="notes" placeholder="Catatan" class="min-w-48 flex-1 rounded-lg border-slate-300"><button wire:click="saveUpdate" class="rounded-lg bg-emerald-600 px-4 py-2 text-white">Simpan</button></div>@else<button wire:click="startUpdate({{ $item->id }})" class="mt-3 text-sm text-emerald-700">Proses</button>@endif</div>@empty<p class="text-slate-500">Tidak ada pengajuan surat.</p>@endforelse</div></div>
