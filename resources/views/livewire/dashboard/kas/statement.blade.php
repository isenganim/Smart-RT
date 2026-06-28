<?php

use App\Services\ExpenseService;
use App\Services\KasReport;
use Illuminate\Support\Carbon;
use function Livewire\Volt\{computed, layout, mount, rules, state, title};

layout('components.layouts.app');
title('Laporan Keuangan Bulanan');

state([
    'month' => null,
    'showExpense' => false,
    'expense_date' => null,
    'expense_amount' => null,
    'expense_category' => '',
    'expense_description' => '',
]);

rules([
    'expense_date' => ['required', 'date'],
    'expense_amount' => ['required', 'integer', 'min:1', 'max:100000000'],
    'expense_category' => ['nullable', 'string', 'max:100'],
    'expense_description' => ['required', 'string', 'min:3', 'max:500'],
]);

mount(function () {
    $requested = (string) request()->query('month', today()->format('Y-m'));
    $this->month = Carbon::canBeCreatedFromFormat($requested, 'Y-m')
        ? $requested
        : today()->format('Y-m');
});

$ref = function () {
    $month = (string) $this->month;

    return Carbon::canBeCreatedFromFormat($month, 'Y-m')
        ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
        : today()->startOfMonth();
};

$statement = computed(function () {
    $ref = $this->ref();

    return app(KasReport::class)->monthlyStatement((int) $ref->year, (int) $ref->month);
});

$openExpense = function () {
    $this->reset('expense_amount', 'expense_category', 'expense_description');
    $this->resetValidation();
    $this->expense_date = today()->toDateString();
    $this->showExpense = true;
};

$closeExpense = function () {
    $this->showExpense = false;
    $this->resetValidation();
};

$saveExpense = function () {
    $this->validate();

    try {
        app(ExpenseService::class)->record(
            Carbon::parse($this->expense_date),
            (int) $this->expense_amount,
            (string) $this->expense_description,
            $this->expense_category !== '' ? (string) $this->expense_category : null,
            auth()->user(),
        );
    } catch (\InvalidArgumentException $exception) {
        $this->addError('expense_amount', $exception->getMessage());

        return;
    }

    $this->reset('showExpense', 'expense_amount', 'expense_category', 'expense_description');
    session()->flash('success', 'Pengeluaran berhasil dicatat.');
};

$rupiah = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');

?>

<div class="space-y-7">
    <x-admin.page-header
        title="Laporan Keuangan Bulanan"
        :description="'Ringkasan saldo, pemasukan, dan pengeluaran untuk '.$this->ref()->translatedFormat('F Y').'.'"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('kas.index') }}">Rekap Kas</x-admin.button>
            <x-admin.button variant="secondary" href="{{ route('kas.statement.export', ['month' => $this->month]) }}">Unduh PDF</x-admin.button>
            <x-admin.button wire:click="openExpense">Catat Pengeluaran</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    @if (session('success'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <x-admin.panel>
        <div
            class="flex max-w-md flex-col gap-3 sm:flex-row sm:items-end"
            x-data="{ raw: @js($this->month) }"
        >
            <div class="min-w-0 flex-1">
                <label for="statement-month" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Bulan</label>
                <input
                    id="statement-month"
                    x-model="raw"
                    type="month"
                    class="tnum mt-2 min-h-11 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink transition focus:border-primary focus:ring-1 focus:ring-primary"
                >
            </div>
            <x-admin.button
                href="#"
                x-bind:href="'{{ route('kas.statement') }}?month=' + raw"
            >Tampilkan</x-admin.button>
        </div>
    </x-admin.panel>

    <section aria-label="Ringkasan keuangan" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.metric label="Saldo Awal" :value="$this->rupiah($this->statement['opening_balance'])" description="Saldo dibawa dari periode sebelumnya" />
        <x-admin.metric label="Total Masuk" :value="$this->rupiah($this->statement['income']['total_in'])" description="Iuran, denda, dan koreksi" />
        <x-admin.metric label="Total Keluar" :value="$this->rupiah($this->statement['total_out'])" description="Seluruh pengeluaran bulan ini" />
        <x-admin.metric label="Saldo Akhir" :value="$this->rupiah($this->statement['closing_balance'])" description="Saldo awal + masuk − keluar" />
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-admin.panel :padding="false" aria-labelledby="income-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="income-title" class="text-lg font-medium text-ink">Pemasukan</h2>
            </div>
            <dl class="divide-y divide-hairline">
                <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                    <dt class="text-sm text-ink-mute">Iuran Harian</dt>
                    <dd class="tnum text-sm font-medium text-ink">{{ $this->rupiah($this->statement['income']['iuran']) }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                    <dt class="text-sm text-ink-mute">Denda Ronda</dt>
                    <dd class="tnum text-sm font-medium text-ink">{{ $this->rupiah($this->statement['income']['denda']) }}</dd>
                </div>
                @if ($this->statement['income']['koreksi'] !== 0)
                    <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                        <dt class="text-sm text-ink-mute">Koreksi</dt>
                        <dd class="tnum text-sm font-medium text-ink">{{ $this->rupiah($this->statement['income']['koreksi']) }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between bg-canvas-soft px-5 py-3 sm:px-6">
                    <dt class="text-sm font-semibold text-ink">Total Pemasukan</dt>
                    <dd class="tnum text-sm font-semibold text-ink">{{ $this->rupiah($this->statement['income']['total_in']) }}</dd>
                </div>
            </dl>
        </x-admin.panel>

        <x-admin.panel :padding="false" aria-labelledby="expense-title">
            <div class="border-b border-hairline px-5 py-4 sm:px-6">
                <h2 id="expense-title" class="text-lg font-medium text-ink">Pengeluaran</h2>
            </div>
            @if ($this->statement['expenses']->isEmpty())
                <x-admin.empty-state
                    title="Belum ada pengeluaran"
                    description="Catat pengeluaran kas untuk bulan ini agar laporan lebih lengkap."
                />
            @else
                <dl class="divide-y divide-hairline">
                    @foreach ($this->statement['expenses'] as $expense)
                        <div class="flex items-center justify-between px-5 py-3 sm:px-6">
                            <dt class="text-sm text-ink-mute">{{ $expense['category'] }}</dt>
                            <dd class="tnum text-sm font-medium text-ruby">-{{ $this->rupiah($expense['amount']) }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between bg-canvas-soft px-5 py-3 sm:px-6">
                        <dt class="text-sm font-semibold text-ink">Total Pengeluaran</dt>
                        <dd class="tnum text-sm font-semibold text-ruby">-{{ $this->rupiah($this->statement['total_out']) }}</dd>
                    </div>
                </dl>
            @endif
        </x-admin.panel>
    </div>

    @if ($showExpense)
        <div
            wire:keydown.escape.window="closeExpense"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="expense-modal-title"
            x-data
            x-init="$nextTick(() => $refs.amount?.focus())"
        >
            <button
                type="button"
                wire:click="closeExpense"
                class="absolute inset-0 cursor-default bg-ink/45 backdrop-blur-sm"
                aria-label="Tutup form pengeluaran"
            ></button>

            <section class="relative z-10 w-full max-w-lg rounded-xl border border-hairline bg-white p-6 shadow-level2">
                <h2 id="expense-modal-title" class="text-lg font-medium text-ink">Catat Pengeluaran</h2>
                <p class="mt-2 text-sm leading-6 text-ink-mute">Pengeluaran tercatat sebagai transaksi kas dan dapat dibatalkan dari daftar transaksi.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="expense-date" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Tanggal</label>
                        <input id="expense-date" type="date" wire:model="expense_date" class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('expense_date') <p class="mt-1 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="expense-amount" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Nominal (Rp)</label>
                        <input id="expense-amount" x-ref="amount" type="number" min="1" wire:model="expense_amount" class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('expense_amount') <p class="mt-1 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="expense-category" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Kategori</label>
                        <input id="expense-category" type="text" list="expense-categories" wire:model="expense_category" placeholder="Lainnya" class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-2.5 text-base text-ink focus:border-primary focus:ring-1 focus:ring-primary">
                        <datalist id="expense-categories">
                            @foreach (\App\Services\ExpenseService::CATEGORIES as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        @error('expense_category') <p class="mt-1 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="expense-description" class="block text-xs font-medium uppercase tracking-[0.08em] text-ink-mute">Keterangan</label>
                        <textarea id="expense-description" rows="2" wire:model="expense_description" class="mt-2 w-full rounded-sm border border-hairline-input bg-white px-4 py-3 text-base text-ink focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        @error('expense_description') <p class="mt-1 text-sm font-medium text-ruby">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-admin.button type="button" variant="secondary" wire:click="closeExpense">Batal</x-admin.button>
                    <x-admin.button type="button" variant="primary" wire:click="saveExpense">Simpan Pengeluaran</x-admin.button>
                </div>
            </section>
        </div>
    @endif
</div>
