<?php

use App\Models\Announcement;
use App\Support\AnnouncementHtml;
use App\Support\Audit;
use function Livewire\Volt\{computed, layout, rules, state, title, usesPagination, with};

usesPagination();

layout('components.layouts.app');
title('Pengumuman');

state([
    'editingId' => null,
    'pendingToggleId' => null,
    'title' => '',
    'body' => '',
]);

rules([
    'title' => ['required', 'string', 'max:255'],
    'body' => ['required', 'string', 'max:5000'],
]);

with(fn () => [
    'announcements' => Announcement::query()->latest()->paginate(10),
    'pendingAnnouncement' => $this->pendingToggleId ? Announcement::find($this->pendingToggleId) : null,
]);

$edit = function (int $id) {
    $item = Announcement::findOrFail($id);

    $this->editingId = $id;
    $this->title = $item->title;
    $this->body = $item->body;
    $this->resetValidation();
    $this->dispatch('announcement-edit-started');
};

$resetForm = function () {
    $this->reset('editingId', 'title', 'body');
    $this->resetValidation();
};

$save = function () {
    $html = app(AnnouncementHtml::class);
    $this->body = $html->sanitize($this->body);

    $data = $this->validate();

    if (! $html->hasVisibleContent($data['body'])) {
        $this->addError('body', 'Isi pengumuman wajib diisi.');

        return;
    }

    if ($this->editingId) {
        $item = Announcement::findOrFail($this->editingId);
        $item->update($data);
        $action = 'announcement.updated';
    } else {
        $data['created_by'] = auth()->id();
        $item = Announcement::create($data);
        $action = 'announcement.created';
    }

    Audit::record(auth()->user(), $action, 'announcement', $item->id, ['title' => $item->title]);
    $this->resetForm();
};

$startToggle = function (int $id) {
    $this->pendingToggleId = $id;
};

$cancelToggle = function () {
    $this->pendingToggleId = null;
};

$confirmToggle = function () {
    $item = Announcement::findOrFail($this->pendingToggleId);
    $publish = ! $item->is_published;

    $item->update([
        'is_published' => $publish,
        'published_at' => $publish ? ($item->published_at ?? now()) : $item->published_at,
    ]);

    Audit::record(
        auth()->user(),
        $publish ? 'announcement.published' : 'announcement.unpublished',
        'announcement',
        $item->id,
    );

    $this->pendingToggleId = null;
};

?>

<div class="space-y-7">
    <x-admin.page-header
        title="Pengumuman"
        description="Tulis informasi untuk warga, simpan sebagai draft, lalu tampilkan saat siap dipublikasikan."
    />

    <x-admin.panel
        x-data
        @announcement-edit-started.window="
            $nextTick(() => {
                $el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                $refs.title.focus();
                $refs.title.select();
            })
        "
    >
        <form wire:submit="save" class="grid gap-5">
            <div>
                <label for="announcement-title" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Judul</label>
                <input
                    id="announcement-title"
                    x-ref="title"
                    wire:model="title"
                    type="text"
                    class="mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                    placeholder="Contoh: Kerja bakti Minggu pagi"
                >
                @error('title') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="announcement-body" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Isi pengumuman</label>
                <p class="mt-1 text-xs leading-5 text-ink-mute">Gunakan format tebal, miring, tautan, atau daftar untuk informasi yang mudah dipindai.</p>
                <div
                    wire:ignore
                    class="announcement-editor mt-3"
                    data-announcement-editor
                    x-data
                    x-init="
                        const editor = $refs.editor;
                        editor.addEventListener('trix-change', () => $wire.$set('body', editor.value, false));
                        $wire.$watch('body', value => {
                            const nextValue = value || '';
                            if (editor.value !== nextValue) editor.editor.loadHTML(nextValue);
                        });
                    "
                >
                    <input id="announcement-body" type="hidden" value="{{ $body }}">
                    <trix-editor
                        x-ref="editor"
                        input="announcement-body"
                        placeholder="Tulis isi pengumuman..."
                        aria-label="Isi pengumuman"
                    ></trix-editor>
                </div>
                @error('body') <p class="mt-2 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                @if ($editingId)
                    <x-admin.button type="button" variant="secondary" wire:click="resetForm">Batal</x-admin.button>
                @endif
                <x-admin.button type="submit">{{ $editingId ? 'Perbarui Draft' : 'Simpan Draft' }}</x-admin.button>
            </div>
        </form>
    </x-admin.panel>

    <x-admin.panel :padding="false" aria-labelledby="announcement-list-title">
        <div class="border-b border-hairline px-5 py-4 sm:px-6">
            <h2 id="announcement-list-title" class="text-lg font-medium text-ink">Daftar pengumuman</h2>
            <p class="mt-1 text-sm text-ink-mute">Pengumuman draft tidak terlihat di Portal Warga.</p>
        </div>

        @forelse ($announcements as $item)
            <article wire:key="announcement-{{ $item->id }}" class="border-b border-hairline px-5 py-5 last:border-b-0 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-medium text-ink">{{ $item->title }}</h3>
                            <x-admin.status-badge :tone="$item->is_published ? 'success' : 'neutral'">
                                {{ $item->is_published ? 'Tampil' : 'Draft' }}
                            </x-admin.status-badge>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-ink-mute">{{ Str::limit(trim(strip_tags($item->body)), 180) }}</p>
                        @if ($item->published_at)
                            <p class="tnum mt-2 text-xs text-ink-mute">Terbit {{ $item->published_at->translatedFormat('d M Y, H:i') }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <x-admin.button variant="secondary" wire:click="edit({{ $item->id }})">Edit</x-admin.button>
                        @if ($item->is_published)
                            <x-admin.button variant="danger" wire:click="startToggle({{ $item->id }})">Sembunyikan</x-admin.button>
                        @else
                            <x-admin.button wire:click="startToggle({{ $item->id }})">Tampilkan</x-admin.button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <x-admin.empty-state
                title="Belum ada pengumuman"
                description="Simpan draft pertama untuk mulai menyiapkan informasi bagi warga."
            />
        @endforelse

        @if ($announcements->hasPages())
            <div class="border-t border-hairline px-5 py-4 sm:px-6">
                {{ $announcements->links() }}
            </div>
        @endif
    </x-admin.panel>

    @if ($pendingAnnouncement)
        <div
            wire:keydown.escape.window="cancelToggle"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="toggle-announcement-title"
            aria-describedby="toggle-announcement-description"
        >
            <button
                type="button"
                wire:click="cancelToggle"
                class="absolute inset-0 cursor-default bg-ink/45 backdrop-blur-sm"
                aria-label="Tutup konfirmasi"
            ></button>

            <section class="relative z-10 w-full max-w-md rounded-xl border border-hairline bg-white p-6 shadow-level2">
                <h2 id="toggle-announcement-title" class="text-xl font-medium text-ink">
                    {{ $pendingAnnouncement->is_published ? 'Sembunyikan Pengumuman?' : 'Tampilkan Pengumuman?' }}
                </h2>
                <p id="toggle-announcement-description" class="mt-3 text-sm leading-6 text-ink-mute">
                    {{ $pendingAnnouncement->is_published
                        ? 'Pengumuman ini tidak akan terlihat di Portal Warga setelah disembunyikan.'
                        : 'Pengumuman ini akan terlihat di Portal Warga setelah ditampilkan.' }}
                </p>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" wire:click="cancelToggle">Batal</x-admin.button>
                    <x-admin.button
                        type="button"
                        variant="{{ $pendingAnnouncement->is_published ? 'danger' : 'primary' }}"
                        wire:click="confirmToggle"
                    >
                        {{ $pendingAnnouncement->is_published ? 'Ya, Sembunyikan' : 'Ya, Tampilkan' }}
                    </x-admin.button>
                </div>
            </section>
        </div>
    @endif
</div>
