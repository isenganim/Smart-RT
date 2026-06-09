# Design Spec: Sprint 4 Playwright Interactive Testing

This document outlines the test scenarios, target URLs, input data, and expected outcomes for verifying Sprint 4 (Layanan Warga) features via interactive browser automation.

## 1. Background & Environment

* **Target URL:** `http://127.0.0.1:32772` / `http://smart-rt.ddev.site` (hosted via DDEV)
* **Resident Test Data:**
  * Registered Resident: Phone `0801-3425-2918` (Normalizes to `80134252918`, Name: `Martani Maryadi S.Sos`)
  * Unregistered Resident: Phone `0899-0000-0000` (Normalizes to `89900000000`)
* **Admin Test Data:**
  * Login: `admin@smartrt.test`
  * Password: `password`

---

## 2. Test Cases & Verification Steps

### Test Case 1: Public Announcements (DT-2)
* **Goal:** Verify unauthenticated users can read published announcements.
* **Steps:**
  1. Navigate to `/pengumuman`.
  2. Verify page loads and list is visible.

### Test Case 2: Lapor Warga (DT-4)
* **Goal:** Verify report submissions are gated by registered phone numbers.
* **Steps:**
  1. Navigate to `/lapor`.
  2. Submit report with unregistered number `089900000000` -> Verify error "Nomor HP belum terdaftar".
  3. Submit report with registered number `080134252918`, category "Keamanan", description "Lampu jalan depan pos ronda mati." -> Verify success "Laporan terkirim".

### Test Case 3: Surat Pengantar (DT-4)
* **Goal:** Verify letter requests are gated by registered phone numbers.
* **Steps:**
  1. Navigate to `/surat`.
  2. Submit request with unregistered number `089900000000` -> Verify error "Nomor HP belum terdaftar".
  3. Submit request with registered number `080134252918`, type "domisili", purpose "Pengurusan KTP baru." -> Verify success "Pengajuan terkirim".

### Test Case 4: Simple Voting (DT-14)
* **Goal:** Verify voting accepts exactly one vote per registered phone number.
* **Steps:**
  1. Log in as admin, navigate to `/dashboard/voting` and create an active voting session. Or seed/tinker one.
  2. Navigate to `/voting/{id}`.
  3. Attempt vote with unregistered number `089900000000` -> Verify error.
  4. Cast vote with registered number `080134252918` -> Verify success "Suara Anda tercatat".
  5. Attempt vote again with the same number `080134252918` -> Verify error "sudah memberikan suara".

### Test Case 5: Admin Workflows (Report & Letter Management)
* **Goal:** Verify admin can log in, view, and update Sprint 4 submissions with audit logs.
* **Steps:**
  1. Navigate to `/login`. Log in as `admin@smartrt.test` / `password`.
  2. Navigate to `/dashboard/laporan`. Locate the report from Test Case 2. Change status to "Selesai" with follow-up note "Sudah diganti bohlamnya." -> Verify updated.
  3. Navigate to `/dashboard/surat`. Locate the letter request from Test Case 3. Change status to "Disetujui" with note "Silakan diambil ke rumah Ketua RT." -> Verify updated.
