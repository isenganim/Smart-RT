# Task 2: Households, Residents & Inventory Dashboard Views Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Households, Residents, and Inventory dashboard views to the premium Stripe-inspired Light Canvas style, utilizing white cards, hairline borders (`border-[#e3e8ee]`), dark navy text (`text-[#0d253d]`), gray text (`text-[#64748d]`), pill-shaped action buttons, and tabular numbers (`tnum`) for numeric formatting, all resting on a cool gray canvas background (`bg-[#f6f9fc]`).

**Architecture:** Update three Livewire/Volt view files: `households/index.blade.php`, `residents/index.blade.php`, and `dashboard/inventory/index.blade.php`. Convert existing dark-blue glass-morphic container panels and styles into high-contrast white panels with hairline borders and light input forms. Keep all logic, Wire bindings, and controller actions unchanged.

**Tech Stack:** Laravel 12, Livewire 4, Volt, Tailwind CSS v4, Pest testing.

---

### Task 1: Redesign Households Index View to Light Canvas

**Files:**
*   Modify: `resources/views/livewire/households/index.blade.php`

- [ ] **Step 1: Redesign the layout container to Light Canvas**
    - Replace the header section background styling and text colors to light styles.
    - Replace the form card container background to white.
    - Replace form inputs and select elements to use light styles with border `#a8c3de`.
    - Update buttons to solid Indigo pill shapes or light gray pill shapes.
    - Replace table section container background and border to white and border `#e3e8ee`.
    - Verify that `tnum` class is present on the house number `<td>` elements and mobile cards.

- [ ] **Step 2: Run test suite to verify no pages fail rendering**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 3: Commit**
    ```bash
    git add resources/views/livewire/households/index.blade.php
    git commit -m "style: redesign households index view to Stripe light canvas theme"
    ```

---

### Task 2: Redesign Residents Index View to Light Canvas

**Files:**
*   Modify: `resources/views/livewire/residents/index.blade.php`

- [ ] **Step 1: Redesign the layout container to Light Canvas**
    - Replace the header section background styling and text colors to light styles.
    - Replace the form card container background to white.
    - Replace form inputs and select elements to use light styles with border `#a8c3de`.
    - Update buttons to solid Indigo pill shapes or light gray pill shapes.
    - Replace table section container background and border to white and border `#e3e8ee`.
    - Verify that `tnum` class is present on phone numbers, household numbers, and ronda notes.

- [ ] **Step 2: Run test suite to verify no pages fail rendering**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 3: Commit**
    ```bash
    git add resources/views/livewire/residents/index.blade.php
    git commit -m "style: redesign residents index view to Stripe light canvas theme"
    ```

---

### Task 3: Redesign Inventory View to Light Canvas

**Files:**
*   Modify: `resources/views/livewire/dashboard/inventory/index.blade.php`

- [ ] **Step 1: Redesign the layout container to Light Canvas**
    - Replace the header section background styling and text colors.
    - Replace the form card container background to white.
    - Update buttons to solid Indigo pill shapes or light gray pill shapes.
    - Update item card grid layout cards to white background.
    - Update Empty state container and Loan dialog/form container.

- [ ] **Step 2: Run test suite to verify no pages fail rendering**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 3: Commit**
    ```bash
    git add resources/views/livewire/dashboard/inventory/index.blade.php
    git commit -m "style: redesign inventory index view to Stripe light canvas theme"
    ```

---

### Task 4: Final Validation and Production Check

- [ ] **Step 1: Run full test suite to guarantee all tests pass**
    Run: `ddev artisan test`
    Expected: PASS

- [ ] **Step 2: Compile production assets**
    Run: `ddev npm run build`
    Expected: Successful build
