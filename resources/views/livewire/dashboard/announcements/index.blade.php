<?php

use App\Models\Announcement;
use App\Support\Audit;
use function Livewire\Volt\{computed, layout, rules, state, title};

layout('components.layouts.app');
title('Pengumuman');
state(['editingId' => null, 'title' => '', 'body' => '']);
rules(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000']]);
$announcements = computed(fn () => Announcement::query()->latest()->get());
$edit = function (int $id) { $item = Announcement::findOrFail($id); $this->editingId = $id; $this->title = $item->title; $this->body = $item->body; };
$resetForm = fn () => $this->reset('editingId', 'title', 'body');
$save = function () {
    $data = $this->validate();
    if ($this->editingId) {
        $item = Announcement::findOrFail($this->editingId); $item->update($data); $action = 'announcement.updated';
    } else {
        $data['created_by'] = auth()->id(); $item = Announcement::create($data); $action = 'announcement.created';
    }
    Audit::record(auth()->user(), $action, 'announcement', $item->id, ['title' => $item->title]); $this->resetForm();
};
$togglePublish = function (int $id) {
    $item = Announcement::findOrFail($id); $publish = ! $item->is_published;
    $item->update(['is_published' => $publish, 'published_at' => $publish ? ($item->published_at ?? now()) : $item->published_at]);
    Audit::record(auth()->user(), $publish ? 'announcement.published' : 'announcement.unpublished', 'announcement', $item->id);
};
?>

<div class="space-y-6"><h1 class="text-2xl font-semibold text-white sm:text-slate-900">Pengumuman</h1>
<form wire:submit="save" class="space-y-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><input wire:model="title" placeholder="Judul" class="w-full rounded-lg border-slate-300">@error('title')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<textarea wire:model="body" rows="4" placeholder="Isi pengumuman" class="w-full rounded-lg border-slate-300"></textarea>@error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<div class="flex gap-2"><button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white">{{ $editingId ? 'Perbarui' : 'Simpan Draft' }}</button>@if($editingId)<button type="button" wire:click="resetForm" class="px-3">Batal</button>@endif</div></form>
<div class="space-y-3">@forelse($this->announcements as $item)<div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200"><div class="flex justify-between gap-4"><div><h2 class="font-medium">{{ $item->title }}</h2><p class="mt-1 text-sm text-slate-600">{{ Str::limit($item->body, 140) }}</p></div><span class="text-xs {{ $item->is_published ? 'text-emerald-600' : 'text-slate-500' }}">{{ $item->is_published ? 'Tampil' : 'Draft' }}</span></div><div class="mt-3 flex gap-3 text-sm"><button wire:click="edit({{ $item->id }})">Edit</button><button wire:click="togglePublish({{ $item->id }})" class="text-emerald-700">{{ $item->is_published ? 'Sembunyikan' : 'Tampilkan' }}</button></div></div>@empty<p class="text-slate-500">Belum ada pengumuman.</p>@endforelse</div></div>
