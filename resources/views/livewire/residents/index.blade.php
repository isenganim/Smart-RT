<?php

use App\Models\Household;
use App\Models\Resident;
use App\Rules\UniqueActivePhone;
use App\Support\Audit;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use function Livewire\Volt\{state, computed, layout, title};

state([
    'editingHouseholdId' => null,
    'memberRows' => [],
]);

layout('components.layouts.app');
title('Data Warga');

$households = computed(fn () => Household::query()
    ->where('is_active', true)
    ->with(['residents' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name')])
    ->orderBy('house_number')
    ->get());

$editingHousehold = computed(fn () => $this->editingHouseholdId
    ? Household::query()
        ->where('is_active', true)
        ->with(['residents' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name')])
        ->find($this->editingHouseholdId)
    : null);

$memberRowFromResident = function (Resident $resident): array {
    return [
        'id' => $resident->id,
        'name' => $resident->name,
        'phone' => $resident->display_phone,
        'ronda_notes' => $resident->ronda_notes ?? '',
        'is_active' => (bool) $resident->is_active,
        '_delete' => false,
    ];
};

$blankMemberRow = fn (): array => [
    'id' => null,
    'name' => '',
    'phone' => '',
    'ronda_notes' => '',
    'is_active' => true,
    '_delete' => false,
];

$openFamilyModal = function (int $householdId) {
    $household = Household::query()
        ->where('is_active', true)
        ->with(['residents' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name')])
        ->findOrFail($householdId);

    $this->editingHouseholdId = $household->id;
    $this->memberRows = $household->residents
        ->map(fn (Resident $resident) => $this->memberRowFromResident($resident))
        ->values()
        ->all();

    if ($this->memberRows === []) {
        $this->addMemberRow();
    }

    $this->resetValidation();
};

$closeFamilyModal = function () {
    $this->reset('editingHouseholdId', 'memberRows');
    $this->resetValidation();
};

$addMemberRow = function () {
    $this->memberRows[] = $this->blankMemberRow();
};

$removeMemberRow = function (int $index) {
    if (! array_key_exists($index, $this->memberRows)) {
        return;
    }

    if (! empty($this->memberRows[$index]['id'])) {
        $this->memberRows[$index]['_delete'] = true;

        return;
    }

    unset($this->memberRows[$index]);
    $this->memberRows = array_values($this->memberRows);
};

$validateFamilyMembers = function (): array {
    $rules = [
        'memberRows' => ['array'],
    ];

    foreach ($this->memberRows as $index => $row) {
        if (! empty($row['_delete'])) {
            continue;
        }

        $residentId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

        $rules["memberRows.$index.id"] = ['nullable', 'integer', 'exists:residents,id'];
        $rules["memberRows.$index.name"] = ['required', 'string', 'max:255'];
        $rules["memberRows.$index.phone"] = ['required', 'string', 'max:30'];
        if ((bool) ($row['is_active'] ?? true)) {
            $rules["memberRows.$index.phone"][] = app(UniqueActivePhone::class, ['ignoreResidentId' => $residentId]);
        }
        $rules["memberRows.$index.ronda_notes"] = ['nullable', 'string', 'max:500'];
        $rules["memberRows.$index._delete"] = ['boolean'];
    }

    return $this->validate($rules);
};

$rejectDuplicatePhonesInRows = function (): bool {
    $seenPhones = [];

    foreach ($this->memberRows as $index => $row) {
        if (! empty($row['_delete'])) {
            continue;
        }

        if (! (bool) ($row['is_active'] ?? true)) {
            continue;
        }

        $normalizedPhone = PhoneNumber::normalize($row['phone'] ?? null);

        if (blank($normalizedPhone)) {
            continue;
        }

        if (array_key_exists($normalizedPhone, $seenPhones)) {
            $this->addError("memberRows.$index.phone", 'Nomor HP sudah dipakai oleh baris anggota lain.');

            return true;
        }

        $seenPhones[$normalizedPhone] = $index;
    }

    return false;
};

$saveFamilyMembers = function () {
    $household = Household::query()
        ->where('is_active', true)
        ->findOrFail($this->editingHouseholdId);

    $this->validateFamilyMembers();

    if ($this->rejectDuplicatePhonesInRows()) {
        return;
    }

    DB::transaction(function () use ($household) {
        foreach ($this->memberRows as $row) {
            $residentId = filled($row['id'] ?? null) ? (int) $row['id'] : null;

            if (! empty($row['_delete'])) {
                if ($residentId) {
                    $resident = Resident::query()
                        ->where('household_id', $household->id)
                        ->findOrFail($residentId);

                    if ($resident->is_active) {
                        $resident->update(['is_active' => false]);
                        Audit::record(auth()->user(), 'resident.removed', 'resident', $resident->id, ['name' => $resident->name]);
                    }
                }

                continue;
            }

            $data = [
                'household_id' => $household->id,
                'name' => $row['name'],
                'phone' => $row['phone'],
                'ronda_notes' => filled($row['ronda_notes'] ?? null) ? $row['ronda_notes'] : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            if ($residentId) {
                $resident = Resident::query()
                    ->where('household_id', $household->id)
                    ->findOrFail($residentId);

                $resident->update($data);
                Audit::record(auth()->user(), 'resident.updated', 'resident', $resident->id, ['name' => $resident->name]);
            } else {
                $resident = Resident::create($data);
                Audit::record(auth()->user(), 'resident.created', 'resident', $resident->id, ['name' => $resident->name]);
            }
        }
    });

    $this->closeFamilyModal();
};

?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="rounded-lg bg-white p-6 border border-[#e3e8ee] shadow-level1 relative overflow-hidden">
        <p class="text-xs font-semibold text-[#64748d] uppercase tracking-wider">Master data</p>
        <h1 class="mt-2 display-lg text-[#0d253d]">Data Warga</h1>
        <p class="mt-2 text-[#64748d]">Kelola anggota keluarga per rumah/KK. Nomor HP tetap unik untuk setiap warga aktif.</p>
    </div>

    <!-- Household Family Section -->
    <section class="rounded-lg bg-white border border-[#e3e8ee] overflow-hidden shadow-level1">
        <div class="border-b border-[#e3e8ee] px-5 py-4">
            <h2 class="text-base font-semibold text-[#0d253d]">Daftar KK dan Anggota</h2>
            <p class="mt-1 text-xs text-[#64748d]">Pilih satu rumah/KK untuk menambah, mengedit, atau menonaktifkan anggota keluarga.</p>
        </div>

        <div class="divide-y divide-[#e3e8ee]">
            @forelse ($this->households as $household)
                @php($activeMembers = $household->residents->where('is_active', true))

                <article class="p-5 hover:bg-[#f6f9fc]/50 transition-colors">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-[#0d253d]">{{ $household->head_name }}</h3>
                                <span class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                                    {{ $activeMembers->count() }} anggota aktif
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-[#64748d]">
                                <span class="tnum">{{ $household->house_number }}</span>
                                @if ($household->address)
                                    <span>&middot; {{ $household->address }}</span>
                                @endif
                            </p>
                        </div>

                        <button type="button" wire:click="openFamilyModal({{ $household->id }})" class="inline-flex items-center justify-center rounded-full bg-[#533afd] px-5 py-3 text-sm font-semibold text-white shadow-level1 transition duration-150 hover:bg-[#4434d4] active:bg-[#2e2b8c]">
                            Kelola Anggota
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($activeMembers as $resident)
                            <div class="rounded-lg border border-[#e3e8ee] bg-[#f6f9fc] p-4">
                                <p class="font-semibold text-[#0d253d]">{{ $resident->name }}</p>
                                <p class="tnum mt-1 text-sm text-[#64748d]">HP: {{ $resident->display_phone }}</p>
                                @if ($resident->ronda_notes)
                                    <p class="mt-3 rounded-sm border border-[#e3e8ee] bg-white px-3 py-2 text-xs text-[#64748d]">{{ $resident->ronda_notes }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-[#e3e8ee] bg-[#f6f9fc] p-4 text-sm text-[#64748d]">
                                Belum ada anggota warga aktif.
                            </div>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="p-8 text-center text-sm text-[#64748d]">
                    Belum ada rumah/KK aktif. Tambahkan rumah terlebih dahulu dari menu Data Rumah.
                </div>
            @endforelse
        </div>
    </section>

    @if ($this->editingHousehold)
        <div wire:keydown.escape.window="closeFamilyModal" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6" role="dialog" aria-modal="true" aria-labelledby="family-modal-title">
            <button type="button" wire:click="closeFamilyModal" class="absolute inset-0 cursor-default bg-[#0d253d]/45 backdrop-blur-sm" aria-label="Tutup modal"></button>

            <section class="relative z-10 max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl border border-[#e3e8ee] bg-white shadow-level2">
                <div class="border-b border-[#e3e8ee] px-6 py-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="family-modal-title" class="heading-md text-[#0d253d]">Kelola Anggota Keluarga</h2>
                            <p class="mt-1 text-sm text-[#64748d]">
                                <span class="tnum">{{ $this->editingHousehold->house_number }}</span>
                                &middot; Kepala keluarga:
                                <span class="font-medium text-[#0d253d]">{{ $this->editingHousehold->head_name }}</span>
                            </p>
                        </div>

                        <button type="button" wire:click="closeFamilyModal" class="rounded-full border border-[#e3e8ee] bg-white px-3 py-1.5 text-xs font-semibold text-[#64748d] transition hover:bg-[#f6f9fc] hover:text-[#0d253d]">
                            Tutup
                        </button>
                    </div>
                </div>

                <form wire:submit="saveFamilyMembers">
                    <div class="space-y-4 px-6 py-6">
                        @foreach ($memberRows as $index => $row)
                            @if (empty($row['_delete']))
                                <div wire:key="member-row-{{ $row['id'] ?? 'new-'.$index }}" class="rounded-lg border border-[#e3e8ee] bg-[#f6f9fc] p-4">
                                    <div class="grid gap-4 lg:grid-cols-12">
                                        <div class="lg:col-span-3">
                                            <label for="member_name_{{ $index }}" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nama</label>
                                            <input wire:model="memberRows.{{ $index }}.name" id="member_name_{{ $index }}" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                                            @error("memberRows.$index.name") <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="lg:col-span-3">
                                            <label for="member_phone_{{ $index }}" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Nomor HP</label>
                                            <input wire:model="memberRows.{{ $index }}.phone" id="member_phone_{{ $index }}" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                                            @error("memberRows.$index.phone") <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="lg:col-span-4">
                                            <label for="member_notes_{{ $index }}" class="block text-xs font-semibold text-[#64748d] uppercase tracking-wider">Catatan Ronda</label>
                                            <input wire:model="memberRows.{{ $index }}.ronda_notes" id="member_notes_{{ $index }}" type="text" class="mt-2 w-full rounded-sm border border-[#a8c3de] bg-white px-4 py-2.5 text-base text-[#0d253d] placeholder-slate-400 transition-all duration-300 focus:border-[#533afd] focus:ring-1 focus:ring-[#533afd]">
                                            @error("memberRows.$index.ronda_notes") <p class="mt-1 text-xs text-[#ea2261] font-medium">{{ $message }}</p> @enderror
                                        </div>

                                        <div class="flex items-end lg:col-span-2">
                                            <button type="button" wire:click="removeMemberRow({{ $index }})" class="w-full rounded-full border border-[#ea2261]/30 bg-[#ea2261]/10 px-4 py-3 text-xs font-semibold text-[#ea2261] transition duration-150 hover:bg-[#ea2261]/15">
                                                Hapus Anggota
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @error('memberRows') <p class="rounded-sm border border-[#ea2261]/30 bg-[#ea2261]/10 p-3 text-sm text-[#ea2261]">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#e3e8ee] bg-[#f6f9fc] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" wire:click="addMemberRow" class="rounded-full border border-[#e3e8ee] bg-white px-5 py-3 text-sm font-semibold text-[#0d253d] transition duration-150 hover:bg-slate-100">
                            Tambah Anggota
                        </button>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" wire:click="closeFamilyModal" class="rounded-full border border-[#e3e8ee] bg-white px-5 py-3 text-sm font-semibold text-[#0d253d] transition duration-150 hover:bg-slate-100">
                                Batal
                            </button>
                            <button class="rounded-full bg-[#533afd] px-5 py-3 text-sm font-semibold text-white shadow-level1 transition duration-150 hover:bg-[#4434d4] active:bg-[#2e2b8c]">
                                Simpan Anggota
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    @endif
</div>
