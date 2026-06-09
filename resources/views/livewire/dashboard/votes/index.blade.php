<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use App\Support\Audit;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.app'); title('Voting');
state(['question' => '', 'optionsText' => '', 'starts_at' => '', 'ends_at' => '']);
rules(['question' => ['required', 'string', 'max:255'], 'optionsText' => ['required', 'string'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);
$votes = computed(fn () => Vote::withCount('ballots')->latest()->get());
$save = function () {
    $this->validate();
    $options = collect(preg_split('/\r\n|\r|\n/', $this->optionsText))->map(fn ($line) => trim($line))->filter()->unique()->values();
    if ($options->count() < 2) { $this->addError('optionsText', 'Minimal dua pilihan berbeda.'); return; }
    $vote = Vote::create(['question' => $this->question, 'status' => VoteStatus::DRAFT, 'starts_at' => $this->starts_at ?: null, 'ends_at' => $this->ends_at ?: null, 'created_by' => auth()->id()]);
    $options->each(fn ($label) => $vote->options()->create(['label' => $label]));
    Audit::record(auth()->user(), 'vote.created', 'vote', $vote->id, ['question' => $vote->question]); $this->reset('question', 'optionsText', 'starts_at', 'ends_at');
};
$activate = function (int $id) { $vote = Vote::withCount('options')->findOrFail($id); if ($vote->options_count < 2) return; $vote->update(['status' => VoteStatus::AKTIF]); Audit::record(auth()->user(), 'vote.activated', 'vote', $id); };
$close = function (int $id) { $vote = Vote::findOrFail($id); $vote->update(['status' => VoteStatus::SELESAI]); Audit::record(auth()->user(), 'vote.closed', 'vote', $id); };
?>
<div class="space-y-6"><h1 class="text-2xl font-semibold text-white sm:text-slate-900">Voting</h1><form wire:submit="save" class="space-y-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><input wire:model="question" placeholder="Pertanyaan voting" class="w-full rounded-lg border-slate-300">@error('question')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<textarea wire:model="optionsText" rows="3" placeholder="Satu pilihan per baris" class="w-full rounded-lg border-slate-300"></textarea>@error('optionsText')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<div class="grid gap-3 sm:grid-cols-2"><input wire:model="starts_at" type="date" class="rounded-lg border-slate-300"><input wire:model="ends_at" type="date" class="rounded-lg border-slate-300"></div>@error('ends_at')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<button class="rounded-lg bg-emerald-600 px-4 py-2 text-white">Simpan Draft</button></form><div class="space-y-3">@foreach($this->votes as $vote)<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div class="flex justify-between gap-4"><div><a href="{{ route('votes.show', $vote) }}" class="font-medium text-emerald-700">{{ $vote->question }}</a><p class="text-xs text-slate-500">{{ $vote->ballots_count }} suara · {{ $vote->status->label() }}</p></div>@if($vote->status === VoteStatus::DRAFT)<button wire:click="activate({{ $vote->id }})" class="text-emerald-700">Aktifkan</button>@elseif($vote->status === VoteStatus::AKTIF)<button wire:click="close({{ $vote->id }})" class="text-red-600">Tutup</button>@endif</div></div>@endforeach</div></div>
