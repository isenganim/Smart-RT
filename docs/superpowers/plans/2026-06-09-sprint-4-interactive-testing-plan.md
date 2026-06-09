# Sprint 4 Playwright Interactive Testing Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Interactively verify all Sprint 4 Layanan Warga features (announcements, reports, letters, and simple voting) on the live site using the Playwright MCP browser.

**Architecture:** Use Playwright MCP commands (`browser_navigate`, `browser_type`, `browser_click`, `browser_fill_form`, etc.) to run the tests on `http://127.0.0.1:32772` and record screenshots to `/home/ageng/.gemini/antigravity-cli/brain/ea8a612e-d74e-4234-b84e-4e91d014565c/` for verification.

**Tech Stack:** Playwright MCP Server, Laravel 12 site running on DDEV.

---

### Task 1: Verify Public Announcements (DT-2)

**Steps:**
- [ ] **Step 1: Navigate to `/pengumuman`**
  - Tool: `browser_navigate`
  - URL: `http://127.0.0.1:32772/pengumuman`
- [ ] **Step 2: Take screenshot & verify content**
  - Tool: `browser_take_screenshot`
  - Action: Capture screenshot of the announcements page and confirm it loads properly.

---

### Task 2: Verify Citizen Reports (DT-4)

**Steps:**
- [ ] **Step 1: Navigate to `/lapor`**
  - Tool: `browser_navigate`
  - URL: `http://127.0.0.1:32772/lapor`
- [ ] **Step 2: Submit with unregistered phone**
  - Tool: `browser_fill_form` & `browser_click`
  - Inputs: Phone: `089900000000`, Kategori: `Keamanan`, Deskripsi: `Lampu mati.`
  - Action: Click "Kirim Laporan".
  - Expected: Show error "Nomor HP belum terdaftar".
- [ ] **Step 3: Submit with registered phone**
  - Tool: `browser_fill_form` & `browser_click`
  - Inputs: Phone: `080134252918`, Kategori: `Keamanan`, Deskripsi: `Lampu jalan depan pos ronda mati total.`
  - Action: Click "Kirim Laporan".
  - Expected: Show success "Laporan terkirim".
- [ ] **Step 4: Take screenshot of submission result**
  - Tool: `browser_take_screenshot`

---

### Task 3: Verify Citizen Letters (DT-4)

**Steps:**
- [ ] **Step 1: Navigate to `/surat`**
  - Tool: `browser_navigate`
  - URL: `http://127.0.0.1:32772/surat`
- [ ] **Step 2: Submit with unregistered phone**
  - Tool: `browser_fill_form` & `browser_click`
  - Inputs: Phone: `089900000000`, Jenis Surat: `domisili`, Keperluan: `Kerja`
  - Expected: Show error "Nomor HP belum terdaftar".
- [ ] **Step 3: Submit with registered phone**
  - Tool: `browser_fill_form` & `browser_click`
  - Inputs: Phone: `080134252918`, Jenis Surat: `domisili`, Keperluan: `Untuk pembukaan rekening bank.`
  - Expected: Show success "Pengajuan terkirim".
- [ ] **Step 4: Take screenshot of request result**
  - Tool: `browser_take_screenshot`

---

### Task 4: Verify Simple Voting (DT-14)

**Steps:**
- [ ] **Step 1: Admin creates and activates a voting session**
  - Tool: Run Tinker command to create an active voting session.
  - Command: `ddev artisan tinker --execute="$v = App\Models\Vote::factory()->open()->create(['question' => 'Pembangunan pos ronda baru?']); App\Models\VoteOption::factory()->for($v)->create(['label' => 'Setuju']); App\Models\VoteOption::factory()->for($v)->create(['label' => 'Tidak Setuju']); echo 'Created vote ID: ' . $v->id;"`
- [ ] **Step 2: Navigate to `/voting`**
  - Tool: `browser_navigate`
  - URL: `http://127.0.0.1:32772/voting`
- [ ] **Step 3: Navigate to the voting detail page**
  - Tool: `browser_navigate` to `/voting/{id}` using the created vote ID.
- [ ] **Step 4: Vote with unregistered phone**
  - Tool: select options, enter phone `089900000000`, click submit.
  - Expected: Show error "Nomor HP belum terdaftar".
- [ ] **Step 5: Vote with registered phone**
  - Tool: enter phone `080134252918`, click submit.
  - Expected: Show success "Suara Anda tercatat".
- [ ] **Step 6: Vote again with the same phone**
  - Tool: enter phone `080134252918`, click submit.
  - Expected: Show error "sudah memberikan suara".
- [ ] **Step 7: Take screenshot of voting state**
  - Tool: `browser_take_screenshot`

---

### Task 5: Verify Admin Follow-Up Workflows

**Steps:**
- [ ] **Step 1: Log in to admin dashboard**
  - Tool: `browser_navigate` to `http://127.0.0.1:32772/login`, enter `admin@smartrt.test` and `password`, click login.
- [ ] **Step 2: Verify and resolve citizen report**
  - Tool: `browser_navigate` to `http://127.0.0.1:32772/dashboard/laporan`, update report status to "Selesai" with note "Sudah diganti".
  - Expected: Status changes to "Selesai" and audit log recorded.
- [ ] **Step 3: Verify and approve letter request**
  - Tool: `browser_navigate` to `http://127.0.0.1:32772/dashboard/surat`, update letter status to "Disetujui" with note "Silakan diambil".
  - Expected: Status changes to "Disetujui" and audit log recorded.
- [ ] **Step 4: Take final screenshots**
  - Tool: `browser_take_screenshot`
