# Smart RT — Sprint Plan

Logical grouping of the 10 phase plans into 5 dependency-ordered sprints. No calendar dates — sprints advance as each completes. Each sprint file lists its phases, dependencies, and definition of done.

| Sprint | Theme | Phases | Depends on |
|--------|-------|--------|------------|
| [Sprint 1](sprint-1-fondasi-data-inti.md) | Fondasi & Data Inti | 01, 02 | — |
| [Sprint 2](sprint-2-portal-jadwal-ronda.md) | Portal Warga & Jadwal Ronda | 03, 04 | Sprint 1 |
| [Sprint 3](sprint-3-kas-ronda-scan-denda.md) | Kas Ronda: Scan Iuran & Denda | 05, 06 | Sprint 1, 2 |
| [Sprint 4](sprint-4-layanan-warga.md) | Layanan Warga | 07, 08, 10 | Sprint 2 |
| [Sprint 5](sprint-5-inventaris.md) | Inventaris | 09 | Sprint 1 |

## Dependency notes

- **Sprint 1** is the bedrock; nothing else starts until it's done.
- **Sprint 2** introduces the Phase 03 `ResidentLookup` phone gateway, reused by all warga-facing actions in Sprints 3–4.
- **Sprint 3** (kas) needs both households+QR (Sprint 1) and ronda attendance (Phase 04 in Sprint 2). Phase 06 consumes Phase 04 + 05 data.
- **Sprint 4** (warga services) depends only on the Phase 03 gateway, so it can run in parallel with Sprint 3.
- **Sprint 5** (inventaris) is isolated — depends only on the foundation, lowest priority, sequenced last.

## Acceptance criteria

Each sprint file has an **Acceptance Criteria** section in Given/When/Then form. Criteria are tagged **DT-1**…**DT-14**, mapping back to the 14 cases in the design spec's "Testing MVP" section (`../specs/2026-05-28-smart-rt-design.md`). All 14 design tests are covered across Sprints 1–4; Sprint 5 (inventaris) has no design test case, so its criteria derive from the data module + pengurus/audit conventions.

| Design test | Covered in |
|-------------|-----------|
| DT-1 admin login + manage data | Sprint 1 |
| DT-2 warga read pengumuman/jadwal | Sprint 2, 4 |
| DT-3 unregistered phone rejected | Sprint 2 |
| DT-4 registered phone official actions | Sprint 2, 4 |
| DT-5 valid PIN opens scan | Sprint 3 |
| DT-6 expired PIN rejected | Sprint 3 |
| DT-7 valid QR records Rp500 | Sprint 3 |
| DT-8 no double payment per date | Sprint 3 |
| DT-9 scheduled warga check-in | Sprint 2 |
| DT-10 unscheduled warga refused | Sprint 2 |
| DT-11 missed check-in → candidate fine | Sprint 3 |
| DT-12 set Rp5.000 fine | Sprint 3 |
| DT-13 cash recap | Sprint 3 |
| DT-14 one vote per phone | Sprint 4 |

## Suggested order

1 → 2 → (3 and 4 in parallel if capacity allows) → 5

Cutting after Sprint 3 yields a working operational MVP (admin, data, portal, ronda, kas). Sprints 4–5 add the remaining warga services and inventory.
