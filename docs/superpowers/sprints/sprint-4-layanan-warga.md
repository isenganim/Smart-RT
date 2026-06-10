# Sprint 4 — Layanan Warga

**Theme:** Warga-facing services behind the phone gateway.

**Goal:** Ship the warga self-service modules: pengumuman + laporan warga, surat pengantar, and voting sederhana.

**Depends on:** Sprint 2 (Phase 03 `ResidentLookup` phone gateway). All three phases gate their official actions behind a registered, active phone number. Independent of the kas work in Sprint 3, so this sprint can run in parallel with Sprint 3 if capacity allows.

**Unlocks:** Rounds out the warga portal feature set. Only inventaris (Sprint 5) remains after this.

## Phases

- [x] **Phase 07 — Pengumuman & Laporan Warga** — `../plans/2026-05-30-phase-07-pengumuman-laporan-warga.md`
  - Pengurus pengumuman CRUD + public display
  - Warga laporan submission (phone-gated), pengurus follow-up status
- [x] **Phase 08 — Surat Pengantar** — `../plans/2026-05-30-phase-08-surat-pengantar.md`
  - Warga surat request (phone-gated): jenis surat, keperluan
  - Pengurus review + status workflow
- [x] **Phase 10 — Voting Sederhana** — `../plans/2026-05-30-phase-10-voting-sederhana.md`
  - Pengurus creates voting (question, options, active period)
  - One vote per registered phone per voting; result tally

## Acceptance Criteria

Mapped to design spec "Testing MVP" → **DT-2** (warga lihat pengumuman), **DT-4** (nomor terdaftar dapat aksi resmi: laporan/surat), **DT-14** (voting satu suara per nomor HP terdaftar).

**AC-4.1 — Public pengumuman (DT-2)**
- Given published pengumuman
- When an unauthenticated warga opens the portal
- Then they can read the pengumuman without login

**AC-4.2 — Laporan gated by phone (DT-4)**
- Given the laporan form
- When a warga submits with a registered active phone
- Then the laporan is saved with pelapor phone, kategori, and deskripsi; submission with an unregistered number is refused

**AC-4.3 — Pengurus follow-up on laporan**
- Given a submitted laporan
- When a pengurus updates its status with a note
- Then the follow-up status and catatan are recorded

**AC-4.4 — Surat request gated by phone (DT-4)**
- Given the surat pengantar form
- When a warga submits with a registered active phone, jenis surat, and keperluan
- Then the request is saved with status pengajuan; an unregistered number is refused

**AC-4.5 — Pengurus advances surat status**
- Given a surat request
- When a pengurus changes its status
- Then the new status and any catatan pengurus are recorded

**AC-4.6 — One vote per registered phone (DT-14)**
- Given an active voting and a registered active phone
- When that phone casts a vote
- Then exactly one vote is counted; a second attempt with the same number is rejected and the result tally stays correct

## Definition of done

- [x] `php artisan test` passes for Phase 07, 08, 10 suites
- [x] Public can read pengumuman; warga can submit laporan with a registered phone
- [x] Pengurus can advance laporan and surat follow-up status
- [x] Warga can submit a surat request gated by phone verification
- [x] Voting accepts exactly one vote per registered phone; results tally correctly
