# Kas Date Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an explicit date submission flow to the cash recap with a stable `dd/mm/yyyy` display.

**Architecture:** Keep the applied date in the existing Volt component and the editable date in local Alpine state. Alpine renders the user-facing `dd/mm/yyyy` value and builds a link to the same route with a canonical `Y-m-d` query parameter.

**Tech Stack:** Laravel, Livewire Volt, Alpine.js, Blade, Pest, Tailwind CSS

---

### Task 1: Add regression coverage

**Files:**
- Modify: `tests/Feature/Kas/KasRekapPageTest.php`

- [ ] **Step 1: Write a failing component test**

Add a feature test that requests `/dashboard/kas?date=2026-06-14` and confirms
the rendered recap uses `14 Juni 2026`. Add a source assertion for the dynamic
`x-bind:href` filter URL.

- [ ] **Step 2: Run the focused test**

Run:

```bash
ddev artisan test tests/Feature/Kas/KasRekapPageTest.php
```

Expected: FAIL because the dynamic filter link does not exist.

### Task 2: Implement the explicit date filter

**Files:**
- Modify: `resources/views/livewire/dashboard/kas/index.blade.php`
- Test: `tests/Feature/Kas/KasRekapPageTest.php`

- [ ] **Step 1: Normalize the applied query date**

Initialize `date` to a valid `Y-m-d` query value and fall back to today for a
malformed value.

- [ ] **Step 2: Replace the overlapping date presentation**

Use an Alpine wrapper with a read-only formatted display, an explicit calendar
button that calls the native `showPicker()` API, and a `Tampilkan` link whose
query string is built from the local selected date.

- [ ] **Step 3: Run focused tests**

Run:

```bash
ddev artisan test tests/Feature/Kas/KasRekapPageTest.php
```

Expected: all tests pass.

### Task 3: Browser verification

**Files:**
- Verify: `resources/views/livewire/dashboard/kas/index.blade.php`

- [ ] **Step 1: Validate desktop behavior with Playwright MCP**

Open `/dashboard/kas`, verify `13/06/2026` appears once, choose another date,
verify the report heading remains unchanged before submission, click
`Tampilkan`, and verify the URL and heading update.

- [ ] **Step 2: Validate mobile layout and console health**

Resize to a mobile viewport, confirm the field and button remain usable, and
confirm there are no relevant console errors or warnings.

- [ ] **Step 3: Run the full relevant verification**

Run:

```bash
ddev artisan test tests/Feature/Kas/KasRekapPageTest.php
```

Expected: all tests pass with zero failures.
