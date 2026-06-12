<?php

use App\Enums\VoteStatus;
use App\Models\Vote;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use function Livewire\Volt\{computed, layout, rules, state, title, usesPagination, with};

usesPagination();

layout('components.layouts.app'); title('Voting');
state(['editingId' => null, 'question' => '', 'optionsText' => '', 'starts_at' => '', 'ends_at' => '']);
rules(['question' => ['required', 'string', 'max:255'], 'optionsText' => ['required', 'string'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']]);

with(fn () => [
    'votes' => Vote::withCount('ballots')->latest()->paginate(10),
]);
$save = function () {
    $this->validate();
    $options = collect(preg_split('/\r\n|\r|\n/', $this->optionsText))->map(fn ($line) => trim($line))->filter()->unique()->values();
    if ($options->count() < 2) { $this->addError('optionsText', 'Minimal dua pilihan berbeda.'); return; }

    $editingVote = null;
    if ($this->editingId) {
        $editingVote = Vote::withCount('ballots')->findOrFail($this->editingId);

        if ($editingVote->status !== VoteStatus::DRAFT) {
            $this->addError('question', 'Voting sudah aktif atau selesai dan tidak dapat diubah.');
            return;
        }

        if ($editingVote->ballots_count > 0) {
            $this->addError('question', 'Voting sudah memiliki suara dan tidak dapat diubah.');
            return;
        }
    }

    DB::transaction(function () use ($options, $editingVote) {
        if ($editingVote) {
            $editingVote->update([
                'question' => $this->question,
                'starts_at' => $this->starts_at ?: null,
                'ends_at' => $this->ends_at ?: null,
            ]);
            $editingVote->options()->delete();
            $options->each(fn ($label) => $editingVote->options()->create(['label' => $label]));
            Audit::record(auth()->user(), 'vote.updated', 'vote', $editingVote->id, ['question' => $editingVote->question]);
        } else {
            $vote = Vote::create(['question' => $this->question, 'status' => VoteStatus::DRAFT, 'starts_at' => $this->starts_at ?: null, 'ends_at' => $this->ends_at ?: null, 'created_by' => auth()->id()]);
            $options->each(fn ($label) => $vote->options()->create(['label' => $label]));
            Audit::record(auth()->user(), 'vote.created', 'vote', $vote->id, ['question' => $vote->question]);
        }
    });

    $this->resetForm();
};
$edit = function (int $id) {
    $vote = Vote::with('options')->findOrFail($id);

    if ($vote->status !== VoteStatus::DRAFT) {
        $this->addError('question', 'Hanya voting draft yang dapat diedit.');
        return;
    }

    $this->editingId = $id;
    $this->question = $vote->question;
    $this->optionsText = $vote->options->pluck('label')->implode("\n");
    $this->starts_at = $vote->starts_at ? \Carbon\Carbon::parse($vote->starts_at)->format('Y-m-d') : '';
    $this->ends_at = $vote->ends_at ? \Carbon\Carbon::parse($vote->ends_at)->format('Y-m-d') : '';
    $this->resetValidation();
};
$resetForm = function () {
    $this->reset('editingId', 'question', 'optionsText', 'starts_at', 'ends_at');
    $this->resetValidation();
};
$activate = function (int $id) {
    $vote = Vote::withCount('options')->findOrFail($id);

    if ($vote->options_count < 2) {
        $this->addError('activation', 'Minimal dua pilihan diperlukan untuk mengaktifkan voting.');
        return;
    }

    $vote->update(['status' => VoteStatus::AKTIF]);
    Audit::record(auth()->user(), 'vote.activated', 'vote', $id);
};
$close = function (int $id) { $vote = Vote::findOrFail($id); $vote->update(['status' => VoteStatus::SELESAI]); Audit::record(auth()->user(), 'vote.closed', 'vote', $id); };
?>
<div class="space-y-7">
    <x-admin.page-header
        title="Voting"
        description="Buat sesi voting untuk warga, tambahkan pilihan jawaban, dan atur periode aktif voting."
    />

    <x-admin.panel>
        <form wire:submit="save" class="grid gap-5">
            <div>
                <label for="vote-question" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Pertanyaan</label>
                <input
                    id="vote-question"
                    wire:model="question"
                    type="text"
                    class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="Contoh: Apakah setuju pembangunan pos ronda baru?"
                >
                @error('question') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="vote-options" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Pilihan Jawaban</label>
                <p class="mt-1 text-xs leading-5 text-ink-mute">Satu pilihan per baris. Minimal dua pilihan berbeda.</p>
                <textarea
                    id="vote-options"
                    wire:model="optionsText"
                    rows="3"
                    class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="Contoh:&#10;Setuju&#10;Tidak Setuju"
                ></textarea>
                @error('optionsText') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="vote-starts-at" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Tanggal Mulai (Opsional)</label>
                    <input
                        id="vote-starts-at"
                        wire:model="starts_at"
                        type="date"
                        class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                </div>
                <div>
                    <label for="vote-ends-at" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Tanggal Selesai (Opsional)</label>
                    <input
                        id="vote-ends-at"
                        wire:model="ends_at"
                        type="date"
                        class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    >
                </div>
            </div>
            @error('ends_at') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror

            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                @if ($editingId)
                    <x-admin.button type="button" variant="secondary" wire:click="resetForm">Batal</x-admin.button>
                @endif
                <x-admin.button type="submit">{{ $editingId ? 'Perbarui Voting' : 'Simpan Draft' }}</x-admin.button>
            </div>
        </form>
    </x-admin.panel>

    @error('activation')
        <div class="rounded-lg bg-ruby/10 border border-ruby/20 px-4 py-3 text-sm text-ruby font-medium">
            {{ $message }}
        </div>
    @enderror

    <x-admin.panel :padding="false" aria-labelledby="vote-list-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="vote-list-title" class="text-lg font-medium text-ink">Daftar voting</h2>
            <p class="mt-1 text-sm text-ink-mute">Voting draft tidak terlihat di Portal Warga.</p>
        </div>

        @forelse ($votes as $item)
            <article wire:key="vote-{{ $item->id }}" class="border-b border-hairline px-5 py-5 last:border-b-0 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('votes.show', $item) }}" class="text-base font-medium text-primary hover:underline">{{ $item->question }}</a>
                            <x-admin.status-badge :tone="$item->status === VoteStatus::AKTIF ? 'success' : ($item->status === VoteStatus::SELESAI ? 'danger' : 'neutral')">
                                {{ $item->status->label() }}
                            </x-admin.status-badge>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-ink-mute">{{ $item->ballots_count }} suara</p>
                        @if ($item->starts_at || $item->ends_at)
                            <p class="tnum mt-2 text-xs text-ink-mute">
                                Periode: 
                                {{ $item->starts_at ? \Carbon\Carbon::parse($item->starts_at)->translatedFormat('d M Y') : 'Mulai sekarang' }}
                                - 
                                {{ $item->ends_at ? \Carbon\Carbon::parse($item->ends_at)->translatedFormat('d M Y') : 'Selesai tanpa batas' }}
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($item->status === VoteStatus::DRAFT)
                            <x-admin.button variant="secondary" wire:click="edit({{ $item->id }})">Edit</x-admin.button>
                            <x-admin.button wire:click="activate({{ $item->id }})">Aktifkan</x-admin.button>
                        @elseif($item->status === VoteStatus::AKTIF)
                            <x-admin.button variant="danger" wire:click="close({{ $item->id }})">Tutup</x-admin.button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <x-admin.empty-state
                title="Belum ada voting"
                description="Simpan draft pertama untuk mulai menyiapkan voting bagi warga."
            />
        @endforelse

        @if ($votes->hasPages())
            <div class="border-t border-hairline px-5 py-4 sm:px-6">
                {{ $votes->links() }}
            </div>
        @endif
    </x-admin.panel>
</div>

