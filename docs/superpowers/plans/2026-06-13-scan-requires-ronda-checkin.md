# Scan Requires Ronda Check-in Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow iuran scanning only during the session window and only for scheduled ronda officers who have checked in.

**Architecture:** `PinGate` distinguishes future, active, and expired matching sessions. A new focused authorization service resolves a resident's ronda assignment for the session date and is called by the portal scan component both during unlock and before each scan.

**Tech Stack:** Laravel 12, Livewire Volt, Eloquent, Pest

---

### Task 1: Distinguish future and expired PIN sessions

**Files:**
- Modify: `tests/Feature/Kas/PinGateTest.php`
- Modify: `app/Services/PinGate.php`

- [x] **Step 1: Add a failing future-session test**

Create a matching session with `starts_at` one hour from now and assert the
message is `Sesi pindai belum dimulai.`

- [x] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
ddev artisan test tests/Feature/Kas/PinGateTest.php
```

Expected: the future-session assertion receives `PIN sudah kedaluwarsa.`

- [x] **Step 3: Select the matching session state explicitly**

Keep active-session selection first. If no active session exists but a matching
session has `starts_at` after `now()`, return `Sesi pindai belum dimulai.`;
otherwise return `PIN sudah kedaluwarsa.`

- [x] **Step 4: Run the PIN gate suite**

Run:

```bash
ddev artisan test tests/Feature/Kas/PinGateTest.php
```

Expected: all PIN gate tests pass.

### Task 2: Authorize checked-in ronda officers

**Files:**
- Create: `app/Services/ScanOfficerGate.php`
- Create: `app/Services/ScanOfficerResult.php`
- Modify: `tests/Feature/Kas/PublicScanIuranTest.php`
- Modify: `resources/views/livewire/portal/scan.blade.php`

- [x] **Step 1: Add failing portal authorization tests**

Add tests proving:

- a registered resident without an assignment is denied;
- an assigned resident without `checked_in_at` is denied;
- an assigned checked-in resident can unlock;
- clearing `checked_in_at` after unlock prevents a transaction on scan.

- [x] **Step 2: Run the portal scan suite and confirm failures**

Run:

```bash
ddev artisan test tests/Feature/Kas/PublicScanIuranTest.php
```

Expected: current unlock succeeds without assignment/check-in and scan records
after authorization is removed.

- [x] **Step 3: Implement the authorization result and service**

`ScanOfficerGate::authorize(Resident $resident, RondaScanSession $session)`
queries the assignment for the session date and resident. Return:

- denied with `Nomor HP tidak terjadwal ronda untuk sesi ini.` when absent;
- denied with `Silakan absen ronda terlebih dahulu sebelum membuka mode pindai.`
  when `checked_in_at` is null;
- allowed when checked in.

- [x] **Step 4: Apply the gate during unlock and scan processing**

After `PinGate` returns a session, authorize the resolved resident before setting
`unlocked`. In `processScan`, resolve the resident and authorize again before
loading or recording the QR transaction. On denial, close scan mode, clear the
session, and show the denial message.

- [x] **Step 5: Run focused and full verification**

Run:

```bash
ddev artisan test tests/Feature/Kas/PinGateTest.php tests/Feature/Kas/PublicScanIuranTest.php tests/Feature/Ronda/RondaCheckinServiceTest.php
ddev artisan test tests/Feature/Kas
git diff --check
```

Expected: all tests pass and no whitespace errors are reported.
