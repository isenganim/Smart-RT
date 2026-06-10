# Sprint 5 Playwright Interactive Testing Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Interactively verify all Sprint 5 Inventaris features (auth-guarding, CRUD, lend/return, condition updates, deactivation, and audits) on the live site using the Playwright MCP browser.

**Architecture:** Use Playwright MCP commands (`browser_navigate`, `browser_type`, `browser_click`, `browser_fill_form`, etc.) to run the tests on `http://127.0.0.1:32772` and record screenshots under `docs/artifacts/sprint-5/` for verification.

**Tech Stack:** Playwright MCP Server, Laravel 12 site running on DDEV.

---

## Task 1: Verify Unauthenticated Redirect (AC-5.1)

**Steps:**
- [ ] **Step 1: Navigate to `/dashboard/inventaris`**
  - Tool: `browser_navigate`
  - URL: `http://127.0.0.1:32772/dashboard/inventaris`
- [ ] **Step 2: Take snapshot & verify redirect**
  - Tool: `browser_snapshot`
  - Expected: The URL redirects to `http://127.0.0.1:32772/login`.

---

## Task 2: Login and Navigate to Dashboard

**Steps:**
- [ ] **Step 1: Fill login form**
  - Tool: `browser_fill_form`
  - Inputs: Email: `admin@smartrt.test`, Password: `password`
- [ ] **Step 2: Click "Masuk"**
  - Tool: `browser_click`
  - Target: The button "Masuk"
  - Expected: Navigates to `/dashboard`.
- [ ] **Step 3: Click "Inventaris" in the sidebar navigation**
  - Tool: `browser_click`
  - Target: The sidebar link "Inventaris"
  - Expected: Navigates to `/dashboard/inventaris` and displays the heading "Inventaris RT".

---

## Task 3: Create Inventory Item (AC-5.2)

**Steps:**
- [ ] **Step 1: Fill the item creation form**
  - Tool: `browser_fill_form`
  - Inputs: Nama Barang: `Tenda Besar`, Kondisi: `Baik`, Lokasi: `Gudang RT`
- [ ] **Step 2: Click "Tambah"**
  - Tool: `browser_click`
  - Target: The button "Tambah"
  - Expected: Form resets, and "Tenda Besar" is added to the table with status "Tersedia" and location "Gudang RT".
- [ ] **Step 3: Take screenshot of the newly created item**
  - Tool: `browser_take_screenshot`
  - Filename: `docs/artifacts/sprint-5/inventory-created.png`

---

## Task 4: Lend & Return Inventory Item

**Steps:**
- [ ] **Step 1: Click "Pinjamkan" next to "Tenda Besar"**
  - Tool: `browser_click`
  - Expected: Form "Pinjamkan Barang" appears below or on the page.
- [ ] **Step 2: Fill borrower name**
  - Tool: `browser_fill_form`
  - Inputs: Peminjam: `Pak Budi`
- [ ] **Step 3: Click the submit "Pinjamkan" button**
  - Tool: `browser_click`
  - Expected: Status shifts to "Dipinjam" and the Lokasi/Peminjam column shows "Pak Budi".
- [ ] **Step 4: Take screenshot of lent item**
  - Tool: `browser_take_screenshot`
  - Filename: `docs/artifacts/sprint-5/inventory-lent.png`
- [ ] **Step 5: Click "Kembalikan"**
  - Tool: `browser_click`
  - Expected: Status shifts back to "Tersedia" and location displays "Gudang RT" again.

---

## Task 5: Update Item Details (AC-5.3)

**Steps:**
- [ ] **Step 1: Click "Edit" next to "Tenda Besar"**
  - Tool: `browser_click`
  - Expected: Form fields are populated with the item's details.
- [ ] **Step 2: Edit condition and location**
  - Tool: `browser_fill_form`
  - Inputs: Kondisi: `Rusak Ringan`, Lokasi: `Sekretariat RT`
- [ ] **Step 3: Click "Perbarui"**
  - Tool: `browser_click`
  - Expected: Item updates in the table displaying "Rusak Ringan" and "Sekretariat RT".
- [ ] **Step 4: Take screenshot of updated item**
  - Tool: `browser_take_screenshot`
  - Filename: `docs/artifacts/sprint-5/inventory-updated.png`

---

## Task 6: Deactivate & Reactivate Item

**Steps:**
- [ ] **Step 1: Click "Nonaktifkan" next to "Tenda Besar"**
  - Tool: `browser_click`
  - Expected: Status shifts to "Tidak Aktif".
- [ ] **Step 2: Take screenshot of deactivated item**
  - Tool: `browser_take_screenshot`
  - Filename: `docs/artifacts/sprint-5/inventory-deactivated.png`
- [ ] **Step 3: Click "Aktifkan"**
  - Tool: `browser_click`
  - Expected: Status shifts back to "Tersedia".

---

## Task 7: Verify DB Audit Logs

**Steps:**
- [ ] **Step 1: Query the audit logs table**
  - Tool: `run_command`
  - Command: `ddev artisan tinker --execute="print_r(App\Models\AuditLog::where('subject_type', 'inventory_item')->get()->pluck('action')->toArray());"`
  - Expected: Output shows audit entries: `['inventory.created', 'inventory.lent', 'inventory.returned', 'inventory.updated', 'inventory.status_changed', 'inventory.status_changed']` (or matching our exact actions sequence).
