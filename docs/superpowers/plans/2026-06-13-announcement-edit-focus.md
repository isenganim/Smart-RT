# Announcement Edit Focus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scroll the announcement form into view and focus its title when an administrator starts editing an existing announcement.

**Architecture:** Reuse the existing Livewire edit action and inline form. Dispatch a browser event after state is populated, then handle it with Alpine on the form panel to scroll and focus without changing persistence behavior.

**Tech Stack:** Laravel 12, Livewire Volt, Alpine.js, Pest

---

### Task 1: Add visible edit navigation

**Files:**
- Modify: `tests/Feature/Sprint4/Sprint4ManagementTest.php`
- Modify: `resources/views/livewire/dashboard/announcements/index.blade.php`

- [x] **Step 1: Write the failing test**

Add source assertions to the existing announcement UI test:

```php
->toContain("\$this->dispatch('announcement-edit-started')")
->toContain('@announcement-edit-started.window')
->toContain("scrollIntoView({ behavior: 'smooth'")
->toContain('$refs.title.focus()')
->toContain('x-ref="title"');
```

- [x] **Step 2: Run the focused test to verify it fails**

Run:

```bash
ddev artisan test tests/Feature/Sprint4/Sprint4ManagementTest.php --filter='rich text editor'
```

Expected: FAIL because the edit dispatch and Alpine listener are absent.

- [x] **Step 3: Implement the minimal behavior**

After populating edit state, dispatch:

```php
$this->dispatch('announcement-edit-started');
```

Add an Alpine listener to the form panel that scrolls the panel into view and
focuses/selects the title input on the next tick. Mark the title input with
`x-ref="title"`.

- [x] **Step 4: Run focused and full verification**

Run:

```bash
ddev artisan test tests/Feature/Sprint4/Sprint4ManagementTest.php
ddev npm run build
git diff --check
```

Expected: all tests pass, Vite exits successfully, and no whitespace errors are reported.
