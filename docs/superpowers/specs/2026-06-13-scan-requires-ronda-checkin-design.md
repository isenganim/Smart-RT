# Scan Requires Ronda Check-in Design

## Goal

Restrict the public iuran scanner to scheduled ronda officers who have already
checked in, while giving accurate feedback for scan sessions that have not
started or have ended.

## Access Rules

- An unknown PIN remains rejected as `PIN tidak ditemukan.`
- A matching session before `starts_at` is rejected as
  `Sesi pindai belum dimulai.`
- A matching session after `ends_at` is rejected as
  `PIN sudah kedaluwarsa.`
- The submitted phone must resolve to an active resident.
- The resident must have a ronda assignment on the scan session date.
- The assignment must have a non-null `checked_in_at`.
- These authorization rules are checked when opening scan mode and again before
  every manual or camera-triggered scan.

## Architecture

`PinGate` remains responsible for resolving the PIN and its time window.
The portal scan component resolves the resident, calls the gate, and checks the
resident's assignment for the returned session date. A small component helper
centralizes assignment authorization so unlock and scan processing use the same
rules and messages.

## Feedback

- Not scheduled: `Nomor HP tidak terjadwal ronda untuk sesi ini.`
- Scheduled but not checked in:
  `Silakan absen ronda terlebih dahulu sebelum membuka mode pindai.`
- If authorization becomes invalid after unlock, scan mode is closed and the
  same relevant message is shown.

## Testing

Add service tests for future and expired PIN distinctions. Add portal component
tests for not scheduled, not checked in, successful checked-in unlock, and
authorization revalidation before recording a transaction.
