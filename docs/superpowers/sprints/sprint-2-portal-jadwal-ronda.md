# Sprint 2 — Portal Warga & Jadwal Ronda

**Theme:** Open the public warga portal and the ronda scheduling + check-in flow.

**Goal:** A no-login warga portal with a reusable phone-verification gateway, plus a ronda schedule per date and phone-based warga check-in.

**Depends on:** Sprint 1 — needs `Resident` + `App\Support\PhoneNumber` (Phase 02) for the lookup service, and the auth/dashboard shell (Phase 01).

**Unlocks:** The Phase 03 phone-verification gateway (`ResidentLookup`) is reused by every warga-facing action in Sprints 3–4 (kas scan context, laporan, surat, voting). Phase 04 ronda attendance is consumed by the denda flow in Sprint 3.

## Phases

- [x] **Phase 03 — Portal Warga Dasar** — `../plans/2026-05-30-phase-03-portal-warga-dasar.md`
  - `ResidentLookup` service + `PhoneLookupResult` typed result
  - Public `x-layouts.public` layout + portal landing page (no auth)
  - Cek Nomor HP page with rate limiting
  - Move `/` to portal; keep `/dashboard` protected
- [x] **Phase 04 — Jadwal Ronda & Check-in** — `../plans/2026-05-30-phase-04-jadwal-ronda-checkin.md`
  - Ronda schedule per date with assigned warga
  - Phone-based check-in (only scheduled, active warga; once per date)
  - Pengurus schedule management
  - Public `/jadwal-ronda` renders as a desktop table with mobile cards
  - Check-in timestamps display with the WIB label

## Acceptance Criteria

Mapped to design spec "Testing MVP" → **DT-2** (warga tanpa login lihat pengumuman/jadwal), **DT-3** (nomor tidak terdaftar ditolak), **DT-4** (nomor terdaftar dapat aksi resmi), **DT-9** (warga terjadwal dapat check-in), **DT-10** (warga tidak terjadwal tidak bisa check-in).

**AC-2.1 — Public portal, no login (DT-2)**
- Given an unauthenticated visitor
- When they open `/`
- Then the warga portal home loads without a login prompt, while `/dashboard` still redirects guests to `/login`

**AC-2.2 — Unregistered phone rejected (DT-3)**
- Given the Cek Nomor HP page
- When a visitor enters a number not held by any active resident
- Then the system responds "Nomor HP belum terdaftar. Silakan hubungi pengurus RT." and does not confirm

**AC-2.3 — Registered phone confirmed (DT-4)**
- Given an active resident with a known phone
- When a visitor enters that number in any format (spaces/dashes/+62)
- Then the system confirms it is registered and active without exposing the resident name

**AC-2.4 — Verification is rate limited**
- Given repeated verification attempts from one IP
- When attempts exceed 5
- Then further attempts are blocked with "Terlalu banyak percobaan"

**AC-2.5 — Scheduled warga check-in (DT-9)**
- Given an active resident scheduled for ronda today who has not yet checked in
- When they submit check-in by phone
- Then attendance is recorded for today; a second check-in on the same date is refused

**AC-2.6 — Unscheduled warga refused (DT-10)**
- Given an active resident not on today's ronda schedule
- When they attempt check-in
- Then the system refuses with a clear reason

**AC-2.7 — Public Ronda schedule table**
- Given a warga visitor on a desktop viewport
- When they open `/jadwal-ronda`
- Then upcoming Ronda schedules are shown in a table with date, notes, assigned residents, and attendance count columns
- And on mobile the same data remains readable as stacked cards

**AC-2.8 — Livewire 4 page updates stay fragment-based**
- Given a Livewire/Volt routed page
- When the page submits or updates via Livewire
- Then the response is component HTML, not a full HTML document, so the browser does not blank or morph incorrectly

**AC-2.9 — Application time uses WIB**
- Given the application records or displays Ronda check-in time
- When Laravel resolves `now()` or formats `checked_in_at`
- Then it uses `Asia/Jakarta` / GMT+7 and user-facing check-in times include `WIB`

## Definition of done

- [x] `php artisan test` passes for Phase 03 + 04 suites
- [x] Portal home loads with no login; `/dashboard` still redirects guests
- [x] Cek Nomor HP confirms a registered active phone and rejects unknown numbers
- [x] Rate limiting blocks excessive verification attempts
- [x] A scheduled warga can check in by phone; unscheduled warga are refused
- [x] Double check-in on the same date is prevented
- [x] `/jadwal-ronda` renders a desktop table and mobile cards
- [x] Livewire/Volt routed pages use `layout()` / `title()` instead of wrapping component output in full layout components
- [x] App timezone is `Asia/Jakarta` and check-in times show `WIB`

## Implementation Notes

- Current verification command: `ddev exec php artisan test` passes with 62 tests and 127 assertions.
- Browser QA covered `/jadwal-ronda` on desktop and mobile using `google-chrome-beta`; desktop table was visible, mobile cards were visible, and console output was clean.
- MCP documentation was checked for Livewire 4 page layout behavior, Livewire loop rendering guidance, and Laravel validation/update patterns. The routed Volt pages now use Volt `layout()` and `title()` helpers so update responses remain fragment-based.
