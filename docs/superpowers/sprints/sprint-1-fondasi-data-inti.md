# Sprint 1 — Fondasi & Data Inti

**Theme:** Bootstrap the application and stand up the master data every other module depends on.

**Goal:** A running Laravel 12 PWA with authenticated pengurus dashboard, roles, and audit logging, plus full CRUD for rumah/KK and warga with unique per-rumah QR tokens and active-phone uniqueness.

**Depends on:** Nothing. This is the bedrock — no other sprint can start until this is complete.

**Unlocks:** Every later sprint. Auth/audit (Phase 01) gate all dashboard work; `Resident` + `PhoneNumber` (Phase 02) gate the portal phone-verification used across Sprints 2–4.

## Phases

- [x] **Phase 01 — Foundation PWA & Auth** — `../plans/2026-05-29-phase-01-foundation-pwa-auth.md`
  - Laravel 12 + Livewire/Volt + Tailwind skeleton
  - Pengurus roles (`admin_rt`, `bendahara`), `EnsurePengurus` middleware
  - Login + dashboard access
  - PWA manifest + service worker
  - Audit log foundation (`audit_logs`, `App\Support\Audit`)
- [x] **Phase 02 — Data Warga, KK, Rumah** — `../plans/2026-05-30-phase-02-data-warga-kk-rumah.md`
  - `Household` model with auto-generated unique `qr_token`
  - `Resident` model with `PhoneNumber` normalizer + `UniqueActivePhone` rule
  - Household management dashboard + QR (SVG) view
  - Resident management dashboard
  - Navigation, dashboard summary counts, demo seeder

## Acceptance Criteria

Mapped to design spec "Testing MVP" → **DT-1** (admin dapat login dan mengelola data warga/rumah).

**AC-1.1 — Pengurus login (DT-1)**
- Given a registered admin_rt user with valid credentials
- When they submit the login form
- Then they are authenticated and redirected to `/dashboard`

**AC-1.2 — Guest is blocked**
- Given an unauthenticated visitor
- When they request `/dashboard`
- Then they are redirected to `/login`

**AC-1.3 — Manage rumah/KK (DT-1)**
- Given an authenticated pengurus on the Rumah/KK page
- When they create a household with nomor rumah and kepala keluarga
- Then the household is saved with a unique `qr_token`, an SVG QR renders, and an audit log `household.created` is recorded

**AC-1.4 — Manage warga with phone uniqueness (DT-1)**
- Given an authenticated pengurus and an existing active resident using a phone number
- When they create a second resident with the same number (any format)
- Then validation rejects it with "Nomor HP sudah terdaftar untuk warga aktif lain"; a unique number normalizes and saves, recording `resident.created`

## Definition of done

- [x] `ddev artisan test` passes for all Phase 01 + 02 suites
- [x] `ddev npm run build` succeeds
- [x] Admin/bendahara can log in; guests are redirected to `/login`
- [x] Households and residents can be created/edited/toggled from the dashboard
- [x] Each household renders a QR token; duplicate active phones are rejected
- [x] Audit log records `household.created` and `resident.created`
