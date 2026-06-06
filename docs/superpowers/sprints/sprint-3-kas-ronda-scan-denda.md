# Sprint 3 — Kas Ronda: Scan Iuran & Denda

**Theme:** The operational heart of the system — daily cash collection and fines recap.

**Goal:** QR-based daily cash collection (Rp500/rumah) gated by a daily PIN session, plus the no-ronda fine (Rp5.000) workflow and the bendahara cash recap.

**Depends on:** Sprint 1 (households + QR tokens) and Sprint 2 (Phase 04 ronda attendance). Phase 06 specifically consumes both ronda check-in data (Phase 04) and kas transactions (Phase 05).

**Unlocks:** Completes the highest-priority operational loop in the design. No later sprint depends on this, so it can be the cut line for an operational MVP.

## Phases

- [x] **Phase 05 — QR Rumah, Sesi PIN & Scan Iuran** — `../plans/2026-05-30-phase-05-qr-pin-scan-iuran.md`
  - Daily ronda PIN session (active window, e.g. 18:00–06:00)
  - Scan QR rumah → show address/head/status → Terima Cash
  - Record Rp500 iuran; one paid record per rumah per date
  - Reject scans without an active PIN; reject expired PINs
- [x] **Phase 06 — Denda Ronda & Rekap Kas** — `../plans/2026-05-30-phase-06-denda-rekap-kas.md`
  - Candidate-fine list from scheduled warga who didn't check in
  - Admin/bendahara review → set Rp5.000 denda
  - Cash recap: daily/weekly/monthly, unpaid houses, missing check-ins
  - Correction/cancellation with reason (no hard deletes)

## Acceptance Criteria

Mapped to design spec "Testing MVP" → **DT-5** (PIN valid buka scan), **DT-6** (PIN kedaluwarsa ditolak), **DT-7** (QR valid catat Rp500), **DT-8** (rumah sama tidak bisa bayar dua kali), **DT-11** (belum check-in jadi calon denda), **DT-12** (admin/bendahara tetapkan denda Rp5.000), **DT-13** (rekap kas harian/mingguan/bulanan).

**AC-3.1 — Valid PIN opens scan (DT-5)**
- Given an active daily PIN session within its time window
- When a petugas enters the PIN on the scan page
- Then scan mode opens

**AC-3.2 — Expired/absent PIN rejected (DT-6)**
- Given no active PIN session, or an expired one
- When a petugas enters a PIN
- Then access is refused with "PIN sudah kedaluwarsa"

**AC-3.3 — QR scan records Rp500 (DT-7)**
- Given scan mode is open and a valid rumah QR token
- When the petugas scans it and presses Terima Cash
- Then a Rp500 iuran transaction is recorded as lunas for today, storing date, time, rumah, nominal, PIN session, and source

**AC-3.4 — No double payment per date (DT-8)**
- Given a rumah already marked lunas for today
- When its QR is scanned again the same date
- Then the system shows "Iuran rumah ini sudah tercatat hari ini" and records no second transaction

**AC-3.5 — Candidate fine list (DT-11)**
- Given scheduled warga who did not check in before the ronda cutoff
- When the cutoff passes
- Then they appear on the calon-denda list for admin/bendahara review

**AC-3.6 — Set fine after review (DT-12)**
- Given a reviewed candidate on the calon-denda list
- When an admin/bendahara confirms the fine
- Then a Rp5.000 denda transaction is recorded with actor and audit trail

**AC-3.7 — Cash recap (DT-13)**
- Given recorded iuran and denda transactions
- When bendahara opens the rekap
- Then daily/weekly/monthly totals, unpaid houses for a date, and missing check-ins are shown

**AC-3.8 — Corrections, not deletes**
- Given a mistaken transaction
- When an admin/bendahara corrects or cancels it
- Then the original is preserved, a correction/cancellation is recorded with a reason, and the action is audited

## Definition of done

- [x] `php artisan test` passes for Phase 05 + 06 suites
- [x] A valid daily PIN opens scan mode; expired/absent PIN is rejected
- [x] Scanning a valid QR records Rp500; the same rumah can't pay twice on one date
- [x] Scheduled warga who missed check-in appear as denda candidates
- [x] Admin/bendahara can set the Rp5.000 fine after review
- [x] Bendahara sees daily/weekly/monthly recap and unpaid-house list
- [x] Corrections are recorded (not deleted) with a reason and audit trail
