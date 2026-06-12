<?php

use App\Models\Vote;
use App\Services\VotingService;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.app'); title('Hasil Voting');
state(['vote' => null]);
mount(fn (Vote $vote) => $this->vote = $vote->load('options'));
$tally = computed(fn () => app(VotingService::class)->tally($this->vote));
$total = computed(fn () => array_sum($this->tally));
?>

<div class="space-y-7">
    <div class="flex flex-col gap-4">
        <div>
            <a href="{{ route('votes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline transition-colors">
                &larr; Kembali ke Daftar Voting
            </a>
        </div>
        <x-admin.page-header
            title="Hasil Voting"
            description="Lihat rincian suara masuk dan persentase pilihan dari warga."
        />
    </div>

    <x-admin.panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-lg font-medium text-ink leading-snug">{{ $vote->question }}</h2>
                <p class="mt-1 text-sm text-ink-mute">{{ $this->total }} suara masuk</p>
            </div>
            <div class="shrink-0">
                <x-admin.status-badge :tone="$vote->status === App\Enums\VoteStatus::AKTIF ? 'success' : ($vote->status === App\Enums\VoteStatus::SELESAI ? 'danger' : 'neutral')">
                    {{ $vote->status->label() }}
                </x-admin.status-badge>
            </div>
        </div>
    </x-admin.panel>

    <x-admin.panel>
        <div class="border-b border-hairline pb-4 mb-6">
            <h3 class="text-base font-medium text-ink">Hasil Pilihan</h3>
            <p class="mt-1 text-sm text-ink-mute">Persentase dan jumlah suara untuk masing-masing pilihan.</p>
        </div>

        <div class="space-y-6">
            @foreach($vote->options as $option)
                @php($count = $this->tally[$option->id] ?? 0)
                @php($pct = $this->total ? round($count / $this->total * 100) : 0)
                <div>
                    <div class="flex justify-between text-sm font-medium">
                        <span class="text-ink">{{ $option->label }}</span>
                        <span class="text-primary tnum">{{ $count }} suara ({{ $pct }}%)</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-hairline-input">
                        <div class="h-full bg-primary" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.panel>
</div>

