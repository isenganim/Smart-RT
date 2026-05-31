# Sprint 5 — Inventaris

**Theme:** Standalone pengurus inventory module.

**Goal:** Basic inventory of RT-owned items: name, condition, location/borrower, status.

**Depends on:** Sprint 1 only (auth/dashboard/audit). No warga-portal or kas dependencies — it's fully isolated.

**Unlocks:** Nothing downstream. Lowest priority in the design, so it's sequenced last and can slip without blocking anything else.

## Phases

- [ ] **Phase 09 — Inventaris Dasar** — `../plans/2026-05-30-phase-09-inventaris-dasar.md`
  - Inventory item CRUD (nama, kondisi, lokasi/peminjam, status)
  - Pengurus-only dashboard module with audit logging

## Acceptance Criteria

The design's "Testing MVP" list has no inventaris-specific case; these derive from the Inventaris data module and the pengurus/audit conventions used across the system.

**AC-5.1 — Pengurus-only access**
- Given an unauthenticated visitor
- When they request the inventaris page
- Then they are redirected to `/login`; only pengurus can open it

**AC-5.2 — Create inventory item**
- Given an authenticated pengurus on the inventaris page
- When they add an item with nama, kondisi, lokasi/peminjam, and status
- Then the item is saved and an audit log entry is recorded

**AC-5.3 — Update item condition and location**
- Given an existing inventory item
- When a pengurus edits its kondisi or lokasi/peminjam
- Then the change is persisted and audited

## Definition of done

- [ ] `php artisan test` passes for the Phase 09 suite
- [ ] Pengurus can add/edit inventory items and track condition + location/borrower
- [ ] Mutations are recorded in the audit log
